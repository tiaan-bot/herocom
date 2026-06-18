<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Enums\EntityType;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Ordering\Models\Cart;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * QA helper: provision (or tear down) a fully-approved test reseller login for
 * exercising the customer-facing portal.
 *
 * Idempotent. Production-guarded (refuses to run there without --force). This is
 * NOT real onboarding: it sets the approved state directly in the DB and never
 * calls an approval action or pushes a contact to Zoho. Tear down with --delete
 * before go-live.
 */
class MakeTestReseller extends Command
{
    protected $signature = 'herocom:make-test-reseller
        {--password= : Password for the test reseller login (required unless --delete)}
        {--force : Allow running in the production environment}
        {--delete : Remove the test reseller user, company and cart rows}';

    protected $description = 'Create or delete a fully-approved QA test reseller login (production-guarded, idempotent)';

    private const EMAIL = 'qa-reseller@herocom.co.za';

    private const COMPANY_LEGAL_NAME = 'QA Test Reseller (DELETE BEFORE GO-LIVE)';

    private const ROLE = 'reseller_owner';

    public function handle(): int
    {
        // Always make it obvious this is throwaway test data.
        $this->warn('This command provisions QA TEST data — run with --delete to remove it before go-live.');

        if (app()->environment('production')) {
            if (! $this->option('force')) {
                $this->error('Refusing to run in production. Re-run with --force if you really intend this.');

                return self::FAILURE;
            }

            $this->warn('Running in PRODUCTION because --force was passed.');
        }

        if ($this->option('delete')) {
            return $this->deleteTestReseller();
        }

        return $this->createTestReseller();
    }

    private function createTestReseller(): int
    {
        $password = (string) $this->option('password');

        if ($password === '') {
            $this->error('The --password option is required (no default secret is baked in).');

            return self::FAILURE;
        }

        if (mb_strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        // The reseller role must already be seeded — never create it ad hoc, so the
        // operator gets the deterministic permission matrix from the seeder.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! Role::where('name', self::ROLE)->where('guard_name', 'web')->exists()) {
            $this->error("The '".self::ROLE."' role does not exist. Seed roles first:");
            $this->line('  php artisan db:seed --class=RolePermissionSeeder --force');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($password): User {
            // Approved state set directly — no approval action, no Zoho push.
            $company = Company::updateOrCreate(
                ['legal_name' => self::COMPANY_LEGAL_NAME],
                [
                    'trading_name' => 'QA Test Reseller',
                    'entity_type' => EntityType::Company,
                    'status' => CompanyStatus::Approved,
                    'approved_at' => now(),
                    'credit_terms' => CreditTerms::EftUpfront,
                    'discount_percent' => 0,
                    'address_line1' => '1 Test Street',
                    'city' => 'Johannesburg',
                    'province' => 'Gauteng',
                    'postal_code' => '2000',
                    'country_code' => 'ZA',
                    'currency' => 'ZAR',
                ],
            );

            $user = User::withTrashed()->where('email', self::EMAIL)->first() ?? new User;

            if ($user->trashed()) {
                $user->restore();
            }

            $user->fill([
                'name' => 'QA Test Reseller',
                'email' => self::EMAIL,
                'company_id' => $company->id,
                'is_active' => true,
            ]);

            // The 'hashed' cast hashes this on save; email + password are set so the
            // user can log in immediately, without the signed set-password link.
            $user->password = $password;
            $user->password_set_at = now();
            $user->email_verified_at = now();
            $user->save();

            if (! $user->hasRole(self::ROLE)) {
                $user->assignRole(self::ROLE);
            }

            return $user;
        });

        $this->info('Test reseller ready:');
        $this->line('  Email: '.$user->email);
        $this->line('  Login: '.url('/login'));

        return self::SUCCESS;
    }

    private function deleteTestReseller(): int
    {
        $removed = DB::transaction(function (): array {
            $user = User::withTrashed()->where('email', self::EMAIL)->first();
            $company = Company::withTrashed()->where('legal_name', self::COMPANY_LEGAL_NAME)->first();

            $carts = 0;
            if ($user !== null) {
                $carts += Cart::where('user_id', $user->id)->delete();
            }
            if ($company !== null) {
                $carts += Cart::where('company_id', $company->id)->delete();
            }

            $user?->forceDelete();
            $company?->forceDelete();

            return ['user' => $user !== null, 'company' => $company !== null, 'carts' => $carts];
        });

        if (! $removed['user'] && ! $removed['company']) {
            $this->info('No test reseller found — nothing to delete.');

            return self::SUCCESS;
        }

        $this->info('Test reseller removed:');
        $this->line('  User: '.($removed['user'] ? 'deleted' : 'not found'));
        $this->line('  Company: '.($removed['company'] ? 'deleted' : 'not found'));
        $this->line('  Cart rows: '.$removed['carts']);

        return self::SUCCESS;
    }
}

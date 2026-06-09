<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Identity\Roles;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bootstrap the first super_admin on a fresh production database.
 *
 * Email is set up later, so the normal invite + signed set-password flow is not
 * available — this command creates (or repairs) an active staff super_admin with
 * a password entered interactively. The password is never accepted as an argument.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin {email : The super admin email address}';

    protected $description = 'Create (or promote) an active staff super_admin with an interactively-entered password';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $email = mb_strtolower(trim($email));

        // The role must already exist — never create it ad hoc here, so the operator
        // gets a deterministic permission matrix from the seeder.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! Role::where('name', Roles::SUPER_ADMIN)->where('guard_name', 'web')->exists()) {
            $this->error("The '".Roles::SUPER_ADMIN."' role does not exist. Seed roles first:");
            $this->line('  php artisan db:seed --class=RolePermissionSeeder --force');

            return self::FAILURE;
        }

        $password = $this->secret('Password (hidden)');
        if ($password === null || $password === '') {
            $this->error('Password cannot be empty.');

            return self::FAILURE;
        }

        if (mb_strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        if ($this->secret('Confirm password (hidden)') !== $password) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $existing = User::withTrashed()->where('email', $email)->first();

        $user = DB::transaction(function () use ($existing, $email, $password): User {
            $user = $existing ?? new User;

            if ($existing && $existing->trashed()) {
                $existing->restore();
            }

            $user->fill([
                'name' => $user->name ?: 'Super Admin',
                'email' => $email,
                'company_id' => null,
                'is_active' => true,
            ]);

            // The 'hashed' cast on the model hashes this on save.
            $user->password = $password;
            $user->password_set_at = now();
            $user->email_verified_at = now();
            $user->save();

            // Idempotent: assigning an already-held role is a no-op. Adding a super_admin
            // never trips the last-active-super_admin guard (that only blocks removal).
            $user->assignRole(Roles::SUPER_ADMIN);

            return $user;
        });

        $this->info(($existing ? 'Updated' : 'Created').' active staff super_admin: '.$user->email);

        return self::SUCCESS;
    }
}

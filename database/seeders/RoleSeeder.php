<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * The application roles, per CLAUDE.md §8.
     *
     * @var list<string>
     */
    private const INTERNAL_ROLES = [
        'super_admin',
        'sales_admin',
        'finance_admin',
        'warranty_admin',
        'support_agent',
        'viewer',
    ];

    /**
     * @var list<string>
     */
    private const RESELLER_ROLES = [
        'reseller_owner',
        'reseller_buyer',
        'reseller_viewer',
    ];

    public function run(): void
    {
        // Ensure cached roles/permissions are fresh before seeding.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = Auth::getDefaultDriver(); // 'web'

        foreach ([...self::INTERNAL_ROLES, ...self::RESELLER_ROLES] as $role) {
            Role::findOrCreate($role, $guard);
        }

        // Bootstrap the first account as super_admin.
        $admin = User::find(1);

        if ($admin === null) {
            $this->command->warn('User id 1 not found — skipped super_admin assignment.');

            return;
        }

        $admin->assignRole('super_admin');

        $this->command->info("Assigned super_admin to user id 1 ({$admin->email}).");
    }
}

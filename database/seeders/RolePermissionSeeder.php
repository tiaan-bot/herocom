<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The full role → permission matrix (spatie/laravel-permission).
 *
 * Idempotent: permissions are firstOrCreate'd and each role's grants are synced,
 * so re-running converges to exactly this matrix. super_admin is synced to an empty
 * permission set — it is never granted abilities directly and instead bypasses all
 * checks via the Gate::before in AppServiceProvider. warranty_admin and support_agent
 * have no permissions yet (also synced to empty).
 */
class RolePermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'view_onboarding_applications',
        'process_onboarding_applications',
        'approve_onboarding_applications',
        'view_catalog',
        'manage_catalog_sync',
        'place_orders',
        'view_orders',
        'manage_orders',
        'view_invoices',
        'manage_company_credit',
        'manage_internal_users',
        'manage_company_users',
    ];

    /**
     * role => permissions granted. super_admin is omitted by design (Gate::before).
     *
     * @var array<string, list<string>>
     */
    private const MATRIX = [
        'sales_admin' => [
            'view_onboarding_applications',
            'process_onboarding_applications',
            'approve_onboarding_applications',
            'view_catalog',
            'view_orders',
            'manage_orders',
        ],
        'finance_admin' => [
            'view_onboarding_applications',
            'manage_company_credit',
            'view_invoices',
            'view_orders',
            'view_catalog',
        ],
        'viewer' => [
            'view_onboarding_applications',
            'view_catalog',
            'view_orders',
            'view_invoices',
        ],
        'reseller_owner' => [
            'view_catalog',
            'place_orders',
            'view_orders',
            'view_invoices',
            'manage_company_users',
        ],
        'reseller_buyer' => [
            'view_catalog',
            'place_orders',
            'view_orders',
            'view_invoices',
        ],
        'reseller_viewer' => [
            'view_catalog',
            'view_orders',
            'view_invoices',
        ],
        'warranty_admin' => [],
        'support_agent' => [],
        // Deliberately empty: super_admin is gate-driven (Gate::before), never granted
        // permissions directly. Synced to empty so any stale grants are cleared on re-run.
        'super_admin' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Ensure all nine canonical roles exist (six internal + three reseller),
        // sourced from the domain constant so this set can never drift from Roles.
        foreach ([...Roles::INTERNAL, ...Roles::RESELLER] as $roleName) {
            Role::findOrCreate($roleName, self::GUARD);
        }

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => self::GUARD,
            ]);
        }

        foreach (self::MATRIX as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, self::GUARD);
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('Synced role → permission matrix ('.count(self::PERMISSIONS).' permissions across '.count(self::MATRIX).' roles).');
    }
}

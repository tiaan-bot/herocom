<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('creates exactly the 12 permissions on the web guard', function () {
    expect(Permission::count())->toBe(12)
        ->and(Permission::where('guard_name', 'web')->count())->toBe(12);
});

it('grants sales_admin its onboarding + order abilities only', function () {
    $role = Role::findByName('sales_admin');

    expect($role->hasPermissionTo('approve_onboarding_applications'))->toBeTrue()
        ->and($role->hasPermissionTo('manage_orders'))->toBeTrue()
        ->and($role->hasPermissionTo('manage_company_credit'))->toBeFalse()
        ->and($role->permissions)->toHaveCount(6);
});

it('grants finance_admin credit management but not onboarding approval', function () {
    $role = Role::findByName('finance_admin');

    expect($role->hasPermissionTo('manage_company_credit'))->toBeTrue()
        ->and($role->hasPermissionTo('view_invoices'))->toBeTrue()
        ->and($role->hasPermissionTo('approve_onboarding_applications'))->toBeFalse();
});

it('grants reseller_owner company-user management but reseller_buyer not', function () {
    expect(Role::findByName('reseller_owner')->hasPermissionTo('manage_company_users'))->toBeTrue()
        ->and(Role::findByName('reseller_buyer')->hasPermissionTo('manage_company_users'))->toBeFalse()
        ->and(Role::findByName('reseller_buyer')->hasPermissionTo('place_orders'))->toBeTrue();
});

it('limits reseller_viewer to read abilities', function () {
    $role = Role::findByName('reseller_viewer');

    expect($role->hasPermissionTo('view_catalog'))->toBeTrue()
        ->and($role->hasPermissionTo('view_orders'))->toBeTrue()
        ->and($role->hasPermissionTo('place_orders'))->toBeFalse();
});

it('leaves warranty_admin and support_agent with no permissions', function () {
    expect(Role::findByName('warranty_admin')->permissions)->toHaveCount(0)
        ->and(Role::findByName('support_agent')->permissions)->toHaveCount(0);
});

it('assigns no explicit permissions to super_admin', function () {
    // super_admin is intentionally absent from the matrix; it relies on Gate::before.
    $superAdmin = Role::findOrCreate('super_admin', 'web');

    expect($superAdmin->permissions)->toHaveCount(0);
});

it('grants super_admin every ability via the Gate::before bypass', function () {
    Role::findOrCreate('super_admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    expect($user->can('manage_company_credit'))->toBeTrue()
        ->and($user->can('manage_internal_users'))->toBeTrue()
        ->and($user->can('manage_catalog_sync'))->toBeTrue()
        // even an ability no role was granted resolves true for super_admin
        ->and($user->can('some_undefined_future_ability'))->toBeTrue();
});

it('enforces real permission checks for non-super users', function () {
    $sales = User::factory()->create();
    $sales->assignRole('sales_admin');

    expect($sales->can('approve_onboarding_applications'))->toBeTrue()
        ->and($sales->can('manage_company_credit'))->toBeFalse();
});

it('is idempotent when run twice', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Permission::count())->toBe(12)
        ->and(Role::findByName('sales_admin')->permissions)->toHaveCount(6);
});

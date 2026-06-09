<?php

declare(strict_types=1);

use App\Domain\Identity\Roles;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates an active staff super admin with a hidden password prompt', function () {
    $this->seed(RolePermissionSeeder::class);

    $this->artisan('app:create-super-admin', ['email' => 'boss@herocom.test'])
        ->expectsQuestion('Password (hidden)', 'sup3r-secret')
        ->expectsQuestion('Confirm password (hidden)', 'sup3r-secret')
        ->assertSuccessful();

    $user = User::where('email', 'boss@herocom.test')->firstOrFail();

    expect($user->company_id)->toBeNull()
        ->and($user->is_active)->toBeTrue()
        ->and($user->password_set_at)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->staffStatus()->value)->toBe('active')
        ->and($user->hasRole(Roles::SUPER_ADMIN))->toBeTrue()
        ->and(Hash::check('sup3r-secret', $user->password))->toBeTrue();
});

it('fails with guidance when the super_admin role has not been seeded', function () {
    $this->artisan('app:create-super-admin', ['email' => 'boss@herocom.test'])
        ->expectsOutputToContain("'super_admin' role does not exist")
        ->assertFailed();

    expect(User::where('email', 'boss@herocom.test')->exists())->toBeFalse();
});

it('rejects mismatched password confirmation', function () {
    $this->seed(RolePermissionSeeder::class);

    $this->artisan('app:create-super-admin', ['email' => 'boss@herocom.test'])
        ->expectsQuestion('Password (hidden)', 'sup3r-secret')
        ->expectsQuestion('Confirm password (hidden)', 'different')
        ->assertFailed();

    expect(User::where('email', 'boss@herocom.test')->exists())->toBeFalse();
});

it('rejects a too-short password', function () {
    $this->seed(RolePermissionSeeder::class);

    $this->artisan('app:create-super-admin', ['email' => 'boss@herocom.test'])
        ->expectsQuestion('Password (hidden)', 'short')
        ->assertFailed();
});

it('is re-runnable: promotes an existing user in place to an active staff super admin', function () {
    $this->seed(RolePermissionSeeder::class);

    $existing = User::factory()->create([
        'email' => 'existing@herocom.test',
        'company_id' => null,
        'is_active' => false,
    ]);

    $this->artisan('app:create-super-admin', ['email' => 'existing@herocom.test'])
        ->expectsQuestion('Password (hidden)', 'new-password-123')
        ->expectsQuestion('Confirm password (hidden)', 'new-password-123')
        ->assertSuccessful();

    $existing->refresh();

    expect(User::where('email', 'existing@herocom.test')->count())->toBe(1)
        ->and($existing->is_active)->toBeTrue()
        ->and($existing->hasRole(Roles::SUPER_ADMIN))->toBeTrue()
        ->and(Hash::check('new-password-123', $existing->password))->toBeTrue();
});

it('seeds exactly the nine canonical roles and twelve permissions idempotently', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class); // re-run must converge, not duplicate

    expect(Role::count())->toBe(9)
        ->and(Permission::count())->toBe(12);
});

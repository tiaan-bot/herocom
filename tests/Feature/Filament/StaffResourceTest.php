<?php

declare(strict_types=1);

use App\Domain\Identity\Roles;
use App\Domain\Onboarding\Models\Company;
use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use App\Notifications\StaffInviteNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function reseller(): User
{
    $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $user->assignRole('reseller_owner');

    return $user;
}

it('grants resource access to super admins only', function () {
    $this->actingAs(userWithRole('super_admin'));
    expect(StaffResource::canAccess())->toBeTrue();

    $this->actingAs(userWithRole('sales_admin'));
    expect(StaffResource::canAccess())->toBeFalse();

    $this->actingAs(reseller());
    expect(StaffResource::canAccess())->toBeFalse();
});

it('denies the policy to non-super-admins', function () {
    expect(userWithRole('super_admin')->can('viewAny', User::class))->toBeTrue()
        ->and(userWithRole('sales_admin')->can('viewAny', User::class))->toBeFalse()
        ->and(reseller()->can('viewAny', User::class))->toBeFalse();
});

it('lists only internal staff, never resellers', function () {
    $admin = userWithRole('super_admin');
    $staff = userWithRole('finance_admin');
    $reseller = reseller();

    $this->actingAs($admin);

    Livewire::test(ListStaff::class)
        ->assertCanSeeTableRecords([$admin, $staff])
        ->assertCanNotSeeTableRecords([$reseller]);
});

it('creates a pending staff member with an internal role and sends the invite', function () {
    Notification::fake();
    $this->actingAs(userWithRole('super_admin'));

    Livewire::test(CreateStaff::class)
        ->fillForm([
            'name' => 'New Staffer',
            'email' => 'newstaffer@herocom.test',
            'roles' => ['sales_admin'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'newstaffer@herocom.test')->sole();

    expect($user->hasRole('sales_admin'))->toBeTrue()
        ->and($user->company_id)->toBeNull()
        ->and($user->password_set_at)->toBeNull()
        ->and($user->staffStatus()->value)->toBe('pending_invite');

    Notification::assertSentTo($user, StaffInviteNotification::class);
});

it('offers only internal roles in the form options (no reseller tiers)', function () {
    $options = Roles::internalOptions();

    expect(array_keys($options))->toBe([
        'super_admin', 'sales_admin', 'finance_admin', 'warranty_admin', 'support_agent', 'viewer',
    ])
        ->and($options)->not->toHaveKeys(['reseller_owner', 'reseller_buyer', 'reseller_viewer']);
});

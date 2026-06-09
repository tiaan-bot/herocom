<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\InviteStaffMemberAction;
use App\Domain\Identity\Actions\SetStaffActiveStatusAction;
use App\Domain\Identity\Actions\SyncStaffRolesAction;
use App\Domain\Identity\Enums\StaffStatus;
use App\Domain\Identity\Exceptions\StaffProtectionException;
use App\Domain\Identity\StaffProtection;
use App\Domain\Onboarding\Models\Company;
use App\Models\User;
use App\Notifications\StaffInviteNotification;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function superAdmin(bool $active = true): User
{
    $u = User::factory()->create(['is_active' => $active, 'password_set_at' => now()]);
    $u->assignRole('super_admin');

    return $u;
}

it('invites a staff member: internal role, no usable password, pending state, email sent', function () {
    Notification::fake();

    $user = app(InviteStaffMemberAction::class)->execute('Jane Staff', 'jane@herocom.test', ['sales_admin']);

    expect($user->hasRole('sales_admin'))->toBeTrue()
        ->and($user->company_id)->toBeNull()
        ->and($user->password_set_at)->toBeNull()
        ->and($user->staffStatus())->toBe(StaffStatus::PendingInvite)
        // The random password is unusable — a known password never matches.
        ->and(Hash::check('password', $user->password))->toBeFalse();

    Notification::assertSentTo($user, StaffInviteNotification::class);
});

it('never assigns a reseller tier through the invite action', function () {
    Notification::fake();

    $user = app(InviteStaffMemberAction::class)->execute('Mix', 'mix@herocom.test', ['sales_admin', 'reseller_owner']);

    expect($user->hasRole('sales_admin'))->toBeTrue()
        ->and($user->hasRole('reseller_owner'))->toBeFalse()
        ->and($user->getRoleNames()->all())->toBe(['sales_admin']);
});

it('never assigns a reseller tier through the role-sync action', function () {
    $actor = superAdmin();
    $target = superAdmin();

    app(SyncStaffRolesAction::class)->execute($target, ['finance_admin', 'reseller_buyer'], $actor);

    expect($target->fresh()->getRoleNames()->all())->toBe(['finance_admin']);
});

it('deactivating blocks web login', function () {
    $user = User::factory()->create([
        'password' => 'secret-password',
        'password_set_at' => now(),
        'is_active' => false,
    ]);
    $user->assignRole('sales_admin');

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('blocks panel access for inactive staff and resellers, allows active staff', function () {
    $panel = Filament::getPanel('admin');

    $active = superAdmin();
    $inactive = superAdmin(active: false);
    $reseller = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $reseller->assignRole('reseller_owner');

    expect($active->canAccessPanel($panel))->toBeTrue()
        ->and($inactive->canAccessPanel($panel))->toBeFalse()
        ->and($reseller->canAccessPanel($panel))->toBeFalse();
});

it('forbids a super admin from deactivating, deleting, or demoting themselves', function () {
    // A second active super admin so the target is not "last active".
    superAdmin();
    $self = superAdmin();
    $protection = app(StaffProtection::class);

    expect(fn () => app(SetStaffActiveStatusAction::class)->execute($self, false, $self))
        ->toThrow(StaffProtectionException::class);
    expect(fn () => $protection->assertCanDelete($self, $self))->toThrow(StaffProtectionException::class);
    expect(fn () => app(SyncStaffRolesAction::class)->execute($self, ['sales_admin'], $self))
        ->toThrow(StaffProtectionException::class);

    // Nothing changed.
    expect($self->fresh()->is_active)->toBeTrue()
        ->and($self->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('protects the last active super admin from deactivation, deletion, and demotion', function () {
    $last = superAdmin();                 // the only ACTIVE super admin
    $actor = superAdmin(active: false);   // a different super admin, but inactive
    $protection = app(StaffProtection::class);

    expect($protection->isLastActiveSuperAdmin($last))->toBeTrue();

    expect(fn () => app(SetStaffActiveStatusAction::class)->execute($last, false, $actor))
        ->toThrow(StaffProtectionException::class);
    expect(fn () => $protection->assertCanDelete($last, $actor))->toThrow(StaffProtectionException::class);
    expect(fn () => app(SyncStaffRolesAction::class)->execute($last, ['sales_admin'], $actor))
        ->toThrow(StaffProtectionException::class);
});

it('soft delete hides the user and blocks login; restore brings them back', function () {
    $user = User::factory()->create([
        'password' => 'secret-password',
        'password_set_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole('sales_admin');

    $user->delete();

    expect(User::query()->find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id))->not->toBeNull();

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $user->restore();
    expect(User::query()->find($user->id))->not->toBeNull();

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);
    $this->assertAuthenticatedAs($user->fresh());
});

<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Regression guard for the admin-login outage: the staff-management migration
 * (is_active + soft deletes) must never lock an existing active staff member
 * out of the admin panel. Each test pins one link in that chain.
 */
it('authenticates an existing active staff user via Auth::attempt', function () {
    $user = User::factory()->create([
        'company_id' => null,
        'is_active' => true,
        'password' => 'known-password',
        'password_set_at' => now(),
    ]);

    expect($user->deleted_at)->toBeNull()
        ->and(Auth::attempt(['email' => $user->email, 'password' => 'known-password']))->toBeTrue()
        ->and(Auth::id())->toBe($user->id);
});

it('allows an active staff user through canAccessPanel for the admin panel', function () {
    $user = User::factory()->create([
        'company_id' => null,
        'is_active' => true,
        'password_set_at' => now(),
    ]);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('defaults users.is_active to true at the database level', function () {
    // Insert a raw row WITHOUT is_active — the column default must apply.
    $id = DB::table('users')->insertGetId([
        'name' => 'Raw Insert',
        'email' => 'raw-insert@herocom.test',
        'password' => Hash::make('irrelevant'),
        'uuid' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('users')->where('id', $id)->first();

    expect((bool) $row->is_active)->toBeTrue();
});

it('blocks an inactive user at login and excludes a soft-deleted user from Auth::attempt', function () {
    $inactive = User::factory()->create([
        'is_active' => false,
        'password' => 'inactive-pw',
        'password_set_at' => now(),
    ]);

    $this->post('/login', ['email' => $inactive->email, 'password' => 'inactive-pw'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $deleted = User::factory()->create([
        'is_active' => true,
        'password' => 'deleted-pw',
        'password_set_at' => now(),
    ]);
    $deleted->delete();

    // SoftDeletes scopes the auth provider query, so trashed rows never match.
    expect(Auth::attempt(['email' => $deleted->email, 'password' => 'deleted-pw']))->toBeFalse();
});

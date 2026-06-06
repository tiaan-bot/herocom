<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function freshUser(): User
{
    return User::factory()->create(['password_set_at' => null]);
}

function signedSetUrl(User $user): string
{
    return URL::temporarySignedRoute('password.set', now()->addDays(7), ['user' => $user->uuid]);
}

it('renders the set-password form for a valid signed link', function () {
    $this->get(signedSetUrl(freshUser()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/SetPassword'));
});

it('sets the password, logs in, and redirects to the catalogue', function () {
    $user = freshUser();

    $this->post(signedSetUrl($user), ['password' => 'sup3rsecret', 'password_confirmation' => 'sup3rsecret'])
        ->assertRedirect('/catalog');

    $this->assertAuthenticatedAs($user->fresh());
    expect($user->fresh()->password_set_at)->not->toBeNull()
        ->and(Hash::check('sup3rsecret', $user->fresh()->password))->toBeTrue();
});

it('shows the expired page for an invalid signature', function () {
    $user = freshUser();

    $this->get(route('password.set', ['user' => $user->uuid])) // unsigned
        ->assertInertia(fn (Assert $page) => $page->component('Auth/SetPasswordExpired'));
});

it('forbids the set-password POST without a valid signature', function () {
    $user = freshUser();

    $this->post(route('password.set.store', ['user' => $user->uuid]), [
        'password' => 'sup3rsecret',
        'password_confirmation' => 'sup3rsecret',
    ])->assertForbidden();
});

it('is single-purpose — a used link redirects to login', function () {
    $user = freshUser();
    $this->post(signedSetUrl($user), ['password' => 'sup3rsecret', 'password_confirmation' => 'sup3rsecret']);

    // Re-using the link after the password is set bounces to login.
    $this->get(signedSetUrl($user))->assertRedirect('/login');
});

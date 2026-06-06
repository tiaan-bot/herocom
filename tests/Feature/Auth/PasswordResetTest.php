<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows the forgot-password page', function () {
    $this->get('/forgot-password')->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
});

it('sends a reset link', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('shows the reset page', function () {
    $this->get('/reset-password/some-token')->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword'));
});

it('resets the password via the broker and redirects to login', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpassw0rd',
        'password_confirmation' => 'newpassw0rd',
    ])->assertRedirect('/login');

    expect(Hash::check('newpassw0rd', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->password_set_at)->not->toBeNull();
});

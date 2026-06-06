<?php

declare(strict_types=1);

use App\Domain\Onboarding\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows the login page', function () {
    $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('redirects a reseller to the catalogue after login', function () {
    $company = Company::factory()->approved()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/catalog');

    $this->assertAuthenticatedAs($user);
});

it('redirects internal staff to the admin panel after login', function () {
    $user = User::factory()->create(['company_id' => null]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/admin');
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('throttles after too many failed attempts', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    // Locked out — even the correct password is now rejected.
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

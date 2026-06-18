<?php

declare(strict_types=1);

use App\Domain\Onboarding\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows the login page', function () {
    $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('redirects a reseller to the catalogue after login', function () {
    $company = Company::factory()->approved()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    // The catalogue is an Inertia page, so a normal 302 redirect is correct.
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertStatus(302)
        ->assertRedirect('/catalog');

    $this->assertAuthenticatedAs($user);
});

it('sends internal staff to the admin panel via an Inertia location visit', function () {
    $user = User::factory()->create(['company_id' => null]);

    // The login form is an Inertia request, but the Filament admin panel is not an
    // Inertia page. Inertia::location() must answer the Inertia client with a 409 +
    // X-Inertia-Location so it performs a full-page visit and the panel boots.
    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => Inertia::getVersion(),
    ])->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('filament.admin.pages.dashboard'));

    $this->assertAuthenticatedAs($user);
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

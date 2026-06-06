<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('serves the home page at / with the Marketing/Home component', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Marketing/Home'));
});

it('shows no authenticated user to a guest (nav renders Sign in / Apply)', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});

it('exposes the company to an authenticated reseller (nav renders Go to portal → /catalog)', function () {
    $user = buyer();

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.company', $user->company->legal_name));
});

it('exposes a company-less user for internal staff (nav renders Go to portal → /admin)', function () {
    $staff = userWithRole('sales_admin');

    $this->actingAs($staff)->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.name', $staff->name)
            ->where('auth.user.company', null));
});

it('does not mention CGIC anywhere on the home page', function () {
    $this->get('/')->assertDontSee('CGIC', false);
});

<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Product;
use App\Domain\Onboarding\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function approvedReseller(float $discount = 0.0): User
{
    $company = Company::factory()->approved()->create(['discount_percent' => $discount]);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('reseller_owner');

    return $user;
}

function pendingReseller(): User
{
    $company = Company::factory()->create(); // status = pending
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('reseller_owner');

    return $user;
}

it('redirects a guest away from the catalog', function () {
    $this->get('/catalog')->assertRedirect('/');
});

it('redirects a pending-company reseller', function () {
    $this->actingAs(pendingReseller())->get('/catalog')->assertRedirect('/');
});

it('lets an approved reseller browse the catalog', function () {
    Product::factory()->create();

    $this->actingAs(approvedReseller())
        ->get('/catalog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Catalog/Index')->has('products.data', 1));
});

it('lets internal staff with view_catalog browse', function () {
    $this->actingAs(userWithRole('viewer'))->get('/catalog')->assertOk();
});

it('forbids an internal user without view_catalog', function () {
    $this->actingAs(userWithRole('warranty_admin'))->get('/catalog')->assertForbidden();
});

it('excludes inactive products from the listing', function () {
    Product::factory()->create(['name' => 'Active One']);
    Product::factory()->inactive()->create(['name' => 'Hidden One']);

    $this->actingAs(approvedReseller())
        ->get('/catalog')
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.name', 'Active One'));
});

it('applies the company discount to displayed prices', function () {
    Product::factory()->create(['rate' => 100, 'name' => 'Widget']);

    $this->actingAs(approvedReseller(discount: 10.0))
        ->get('/catalog')
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.list_price', fn ($v) => (float) $v === 100.0)
            ->where('products.data.0.your_price', fn ($v) => (float) $v === 90.0));
});

it('shows an active product detail but 404s an inactive one', function () {
    $reseller = approvedReseller();
    $active = Product::factory()->create();
    $inactive = Product::factory()->inactive()->create();

    $this->actingAs($reseller)
        ->get("/catalog/{$active->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Catalog/Show')->where('product.uuid', $active->uuid));

    $this->actingAs($reseller)->get("/catalog/{$inactive->uuid}")->assertNotFound();
});

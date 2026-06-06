<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('serves the products page at /products with the Marketing/Products component', function () {
    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Marketing/Products'));
});

it('lists flagged featured products first, ordered by name', function () {
    Product::factory()->featured()->create(['name' => 'Zebra Switch']);
    Product::factory()->featured()->create(['name' => 'Alpha Camera']);
    // An inactive featured product must never surface on the public page.
    Product::factory()->featured()->inactive()->create(['name' => 'Hidden NVR']);
    Product::factory()->count(3)->create(); // active, non-featured fill candidates

    // 2 active featured + 3 fill = 5; the inactive featured is excluded, proven by
    // index 1 being Zebra (not the alphabetically-earlier "Hidden NVR").
    $this->get('/products')->assertInertia(fn (Assert $page) => $page
        ->has('featured', 5)
        ->where('featured.0.name', 'Alpha Camera')
        ->where('featured.1.name', 'Zebra Switch'));
});

it('caps featured at 8 even when more are flagged', function () {
    Product::factory()->count(10)->featured()->create();

    $this->get('/products')->assertInertia(fn (Assert $page) => $page->has('featured', 8));
});

it('fills with the most recently synced products when fewer than 8 are flagged', function () {
    Product::factory()->featured()->create(['name' => 'Flagged']);
    $old = Product::factory()->create(['name' => 'Older', 'last_synced_at' => now()->subDays(10)]);
    $new = Product::factory()->create(['name' => 'Newer', 'last_synced_at' => now()]);

    $this->get('/products')->assertInertia(fn (Assert $page) => $page
        ->has('featured', 3)
        ->where('featured.0.name', 'Flagged')        // flagged first
        ->where('featured.1.name', 'Newer')          // then most recent
        ->where('featured.2.name', 'Older'));
});

it('reports real active-product counts per category', function () {
    Product::factory()->count(3)->create(['category' => 'Networking']);
    Product::factory()->create(['category' => 'Storage']);
    Product::factory()->inactive()->create(['category' => 'Networking']); // excluded

    $this->get('/products')->assertInertia(fn (Assert $page) => $page
        ->where('categoryCounts.Networking', 3)
        ->where('categoryCounts.Storage', 1)
        ->where('categoryCounts.Power', 0)
        ->where('categoryCounts.Surveillance', 0)
        ->where('categoryCounts.Peripherals', 0));
});

it('does not mention CGIC anywhere on the products page', function () {
    $this->get('/products')->assertDontSee('CGIC', false);
});

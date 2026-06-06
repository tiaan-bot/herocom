<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Ordering\Enums\CartStatus;
use App\Domain\Ordering\Models\Cart;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('adds a product to the cart', function () {
    $buyer = buyer();
    $product = Product::factory()->create();

    $this->actingAs($buyer)->post('/cart', ['product' => $product->uuid, 'quantity' => 2])->assertRedirect();

    $cart = Cart::query()->where('user_id', $buyer->id)->where('status', CartStatus::Open)->sole();
    expect($cart->items)->toHaveCount(1)
        ->and((float) $cart->items->first()->quantity)->toBe(2.0);
});

it('increments quantity when the same product is added again', function () {
    $buyer = buyer();
    $product = Product::factory()->create();

    $this->actingAs($buyer)->post('/cart', ['product' => $product->uuid, 'quantity' => 1]);
    $this->actingAs($buyer)->post('/cart', ['product' => $product->uuid, 'quantity' => 3]);

    $cart = Cart::query()->where('user_id', $buyer->id)->sole();
    expect($cart->items)->toHaveCount(1)
        ->and((float) $cart->items->first()->quantity)->toBe(4.0);
});

it('keeps a single open cart per user across adds', function () {
    $buyer = buyer();
    Product::factory()->count(2)->create()->each(
        fn (Product $p) => $this->actingAs($buyer)->post('/cart', ['product' => $p->uuid])
    );

    expect(Cart::query()->where('user_id', $buyer->id)->where('status', CartStatus::Open)->count())->toBe(1);
});

it('updates a cart item quantity, removing it at zero', function () {
    $buyer = buyer();
    $product = Product::factory()->create();
    $this->actingAs($buyer)->post('/cart', ['product' => $product->uuid, 'quantity' => 5]);
    $item = Cart::query()->where('user_id', $buyer->id)->sole()->items->first();

    $this->actingAs($buyer)->patch("/cart/items/{$item->id}", ['quantity' => 2])->assertRedirect();
    expect((float) $item->fresh()->quantity)->toBe(2.0);

    $this->actingAs($buyer)->patch("/cart/items/{$item->id}", ['quantity' => 0]);
    expect($item->fresh())->toBeNull();
});

it('removes a cart item', function () {
    $buyer = buyer();
    $product = Product::factory()->create();
    $this->actingAs($buyer)->post('/cart', ['product' => $product->uuid]);
    $item = Cart::query()->where('user_id', $buyer->id)->sole()->items->first();

    $this->actingAs($buyer)->delete("/cart/items/{$item->id}")->assertRedirect();
    expect($item->fresh())->toBeNull();
});

it('blocks a reseller_viewer from mutating the cart', function () {
    $company = Company::factory()->approved()->create();
    $viewer = User::factory()->create(['company_id' => $company->id]);
    $viewer->assignRole('reseller_viewer'); // view_catalog but no place_orders
    $product = Product::factory()->create();

    $this->actingAs($viewer)->post('/cart', ['product' => $product->uuid])->assertForbidden();
    expect(Cart::query()->count())->toBe(0);
});

it('cannot mutate another users cart item', function () {
    $owner = buyer();
    $product = Product::factory()->create();
    $this->actingAs($owner)->post('/cart', ['product' => $product->uuid]);
    $item = Cart::query()->where('user_id', $owner->id)->sole()->items->first();

    $this->actingAs(buyer())->delete("/cart/items/{$item->id}")->assertForbidden();
    expect($item->fresh())->not->toBeNull();
});

it('flags an inactive product line and excludes it from the subtotal', function () {
    $buyer = buyer(discount: 10.0);
    $active = Product::factory()->create(['rate' => 100]);
    $inactive = Product::factory()->create(['rate' => 50]);
    $this->actingAs($buyer)->post('/cart', ['product' => $active->uuid]);
    $this->actingAs($buyer)->post('/cart', ['product' => $inactive->uuid]);
    $inactive->update(['status' => ProductStatus::Inactive]);

    $this->actingAs($buyer)->get('/cart')->assertInertia(fn ($page) => $page
        ->component('Cart/Index')
        ->where('hasUnavailable', true)
        ->where('subtotal', fn ($v) => (float) $v === 90.0)); // only the active line (100 − 10%)
});

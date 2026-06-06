<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Enums\CartStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderPlaced;
use App\Domain\Ordering\Models\Cart;
use App\Domain\Ordering\Models\Order;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function deliveryPayload(array $overrides = []): array
{
    return array_replace([
        'delivery_address_line1' => '1 Warehouse Rd',
        'delivery_city' => 'Johannesburg',
        'delivery_province' => 'Gauteng',
        'delivery_postal_code' => '2000',
        'delivery_country_code' => 'ZA',
        'customer_note' => 'Leave at reception',
    ], $overrides);
}

it('places an order from the cart and closes the cart', function () {
    $buyer = buyer(discount: 10.0);
    $product = Product::factory()->create(['rate' => 100, 'zoho_item_id' => 'z-1']);
    $this->actingAs($buyer)->post('/cart', ['product' => $product->uuid, 'quantity' => 2]);

    Event::fake([OrderPlaced::class]);
    $this->actingAs($buyer)->post('/checkout', deliveryPayload())->assertRedirect(route('checkout.success'));

    $order = Order::query()->sole();
    expect($order->status)->toBe(OrderStatus::Placed)
        ->and($order->items)->toHaveCount(1)
        ->and((float) $order->subtotal_ex_vat)->toBe(180.0) // 2 × (100 − 10%)
        ->and($order->order_number)->toStartWith('HD-')
        ->and((float) $order->discount_percent_applied)->toBe(10.0);

    Event::assertDispatched(OrderPlaced::class);
    expect(Cart::query()->where('user_id', $buyer->id)->where('status', CartStatus::Open)->count())->toBe(0);
});

it('snapshots prices — a later catalog price change does not alter the order', function () {
    $buyer = buyer(discount: 0.0);
    $product = Product::factory()->create(['rate' => 100, 'zoho_item_id' => 'z-1']);
    $this->actingAs($buyer)->post('/cart', ['product' => $product->uuid]);

    Event::fake([OrderPlaced::class]);
    $this->actingAs($buyer)->post('/checkout', deliveryPayload());

    $order = Order::query()->sole();
    expect((float) $order->items->first()->unit_price)->toBe(100.0);

    $product->update(['rate' => 500]);
    expect((float) $order->items()->first()->unit_price)->toBe(100.0);
});

it('snapshots the delivery address and note onto the order', function () {
    $buyer = buyer();
    $product = Product::factory()->create();
    $this->actingAs($buyer)->post('/cart', ['product' => $product->uuid]);

    Event::fake([OrderPlaced::class]);
    $this->actingAs($buyer)->post('/checkout', deliveryPayload(['delivery_city' => 'Pretoria']));

    $order = Order::query()->sole();
    expect($order->delivery_city)->toBe('Pretoria')
        ->and($order->customer_note)->toBe('Leave at reception');
});

it('rejects checkout with an empty cart', function () {
    $buyer = buyer();

    $this->actingAs($buyer)->post('/checkout', deliveryPayload())->assertRedirect(route('cart.index'));

    expect(Order::query()->count())->toBe(0);
});

it('redirects the checkout page to the cart when empty', function () {
    $this->actingAs(buyer())->get('/checkout')->assertRedirect(route('cart.index'));
});

it('excludes an inactive line from the placed order', function () {
    $buyer = buyer();
    $active = Product::factory()->create();
    $inactive = Product::factory()->create();
    $this->actingAs($buyer)->post('/cart', ['product' => $active->uuid]);
    $this->actingAs($buyer)->post('/cart', ['product' => $inactive->uuid]);
    $inactive->update(['status' => ProductStatus::Inactive]);

    Event::fake([OrderPlaced::class]);
    $this->actingAs($buyer)->post('/checkout', deliveryPayload());

    expect(Order::query()->sole()->items)->toHaveCount(1);
});

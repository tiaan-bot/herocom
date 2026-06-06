<?php

declare(strict_types=1);

use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('lists only the buyer\'s own company orders', function () {
    $a = buyer();
    $b = buyer();
    Order::factory()->count(2)->create(['company_id' => $a->company_id, 'placed_by' => $a->id]);
    Order::factory()->create(['company_id' => $b->company_id, 'placed_by' => $b->id]);

    $this->actingAs($a)->get('/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Orders/Index')->has('orders.data', 2));
});

it('forbids viewing another company\'s order', function () {
    $a = buyer();
    $b = buyer();
    $order = Order::factory()->create(['company_id' => $b->company_id, 'placed_by' => $b->id]);

    $this->actingAs($a)->get("/orders/{$order->uuid}")->assertForbidden();
});

it('shows an order detail to its own company', function () {
    $a = buyer();
    $order = Order::factory()
        ->has(OrderItem::factory()->count(2), 'items')
        ->create(['company_id' => $a->company_id, 'placed_by' => $a->id]);

    $this->actingAs($a)->get("/orders/{$order->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Orders/Show')
            ->where('order.number', $order->order_number)
            ->has('order.lines', 2));
});

it('redirects a guest to login', function () {
    $order = Order::factory()->create();
    $this->get("/orders/{$order->uuid}")->assertRedirect('/login');
});

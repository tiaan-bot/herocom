<?php

declare(strict_types=1);

use App\Domain\Onboarding\Models\Company;
use App\Domain\Ordering\Actions\AcceptOrderAction;
use App\Domain\Ordering\Actions\RejectOrderAction;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\ZohoPushStatus;
use App\Domain\Ordering\Exceptions\OrderException;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Shared\Zoho\Models\ZohoToken;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Notifications\OrderStatusNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('accepts a placed order and emails the placer', function () {
    Notification::fake();
    $buyer = buyer();
    $order = Order::factory()->create(['company_id' => $buyer->company_id, 'placed_by' => $buyer->id, 'status' => OrderStatus::Placed]);

    app(AcceptOrderAction::class)->execute($order, userWithRole('sales_admin'));

    expect($order->fresh()->status)->toBe(OrderStatus::Accepted)
        ->and($order->fresh()->accepted_by)->not->toBeNull();
    Notification::assertSentTo($buyer, OrderStatusNotification::class);
});

it('rejects a placed order with a reason and emails the placer', function () {
    Notification::fake();
    $buyer = buyer();
    $order = Order::factory()->create(['company_id' => $buyer->company_id, 'placed_by' => $buyer->id, 'status' => OrderStatus::Placed]);

    app(RejectOrderAction::class)->execute($order, userWithRole('sales_admin'), 'Out of stock');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Rejected)
        ->and($order->rejection_reason)->toBe('Out of stock');
    Notification::assertSentTo($buyer, OrderStatusNotification::class);
});

it('refuses to accept an order that is not placed', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Accepted]);

    expect(fn () => app(AcceptOrderAction::class)->execute($order, userWithRole('sales_admin')))
        ->toThrow(OrderException::class);
});

it('denies accept to a user without manage_orders', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Placed]);

    expect(fn () => app(AcceptOrderAction::class)->execute($order, userWithRole('viewer')))
        ->toThrow(AuthorizationException::class);
});

it('accepts an order through the Filament action', function () {
    Notification::fake();
    $buyer = buyer();
    $order = Order::factory()->create(['company_id' => $buyer->company_id, 'placed_by' => $buyer->id, 'status' => OrderStatus::Placed]);

    $this->actingAs(userWithRole('sales_admin'));
    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->callAction('accept');

    expect($order->fresh()->status)->toBe(OrderStatus::Accepted);
});

it('retries a failed Zoho push through the Filament action', function () {
    config([
        'zoho.client_id' => 'cid', 'zoho.client_secret' => 'secret', 'zoho.organization_id' => 'org-1',
        'zoho.accounts_domain' => 'accounts.zoho.com', 'zoho.api_domain' => 'www.zohoapis.com',
    ]);
    ZohoToken::query()->create(['refresh_token' => 'r', 'access_token' => 'valid', 'access_token_expires_at' => now()->addHour()]);
    Http::preventStrayRequests();
    Http::fake(['*/books/v3/salesorders*' => Http::response(['salesorder' => ['salesorder_id' => 'so-retry']])]);

    $company = Company::factory()->approved()->create(['zoho_customer_id' => 'cust-1']);
    $order = Order::factory()->failed()->create(['company_id' => $company->id, 'zoho_salesorder_id' => null, 'status' => OrderStatus::Placed]);
    OrderItem::factory()->create(['order_id' => $order->id]);

    $this->actingAs(userWithRole('sales_admin'));
    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->callAction('retryPush');

    $order->refresh();
    expect($order->zoho_push_status)->toBe(ZohoPushStatus::Pushed)
        ->and($order->zoho_salesorder_id)->toBe('so-retry');
});

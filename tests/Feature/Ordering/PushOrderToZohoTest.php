<?php

declare(strict_types=1);

use App\Domain\Onboarding\Models\Company;
use App\Domain\Ordering\Enums\ZohoPushStatus;
use App\Domain\Ordering\Events\OrderPlaced;
use App\Domain\Ordering\Listeners\PushOrderToZoho;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Shared\Zoho\Exceptions\ZohoException;
use App\Domain\Shared\Zoho\Models\ZohoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'zoho.client_id' => 'cid',
        'zoho.client_secret' => 'secret',
        'zoho.organization_id' => 'org-1',
        'zoho.accounts_domain' => 'accounts.zoho.com',
        'zoho.api_domain' => 'www.zohoapis.com',
        'zoho.retry.max_attempts' => 3,
        'zoho.retry.base_backoff_ms' => 1,
    ]);
    ZohoToken::query()->create([
        'refresh_token' => 'r',
        'access_token' => 'valid',
        'access_token_expires_at' => now()->addHour(),
    ]);
    Http::preventStrayRequests();
});

function orderFor(Company $company): Order
{
    $order = Order::factory()->create(['company_id' => $company->id, 'zoho_salesorder_id' => null]);
    OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

    return $order->load('items');
}

it('pushes a sales order and stores the id', function () {
    Http::fake(['*/books/v3/salesorders*' => Http::response(['salesorder' => ['salesorder_id' => 'so-1']])]);
    $order = orderFor(Company::factory()->approved()->create(['zoho_customer_id' => 'cust-1']));

    app(PushOrderToZoho::class)->handle(new OrderPlaced($order));

    $order->refresh();
    expect($order->zoho_salesorder_id)->toBe('so-1')
        ->and($order->zoho_push_status)->toBe(ZohoPushStatus::Pushed)
        ->and($order->zoho_pushed_at)->not->toBeNull();
});

it('is idempotent — an already-pushed order is not re-sent', function () {
    Http::fake(['*' => Http::response([], 500)]);
    $order = Order::factory()->pushed()->create();

    app(PushOrderToZoho::class)->handle(new OrderPlaced($order));

    Http::assertNothingSent();
});

it('creates the Zoho customer first when the company has none', function () {
    Http::fake([
        '*/books/v3/contacts*' => Http::response(['contact' => ['contact_id' => 'cust-new']]),
        '*/books/v3/salesorders*' => Http::response(['salesorder' => ['salesorder_id' => 'so-2']]),
    ]);
    $company = Company::factory()->approved()->create(['zoho_customer_id' => null]);
    $order = orderFor($company);

    app(PushOrderToZoho::class)->handle(new OrderPlaced($order));

    expect($company->fresh()->zoho_customer_id)->toBe('cust-new')
        ->and($order->fresh()->zoho_salesorder_id)->toBe('so-2');
});

it('marks the push failed and rethrows on a Zoho error', function () {
    Sleep::fake();
    Http::fake(['*/books/v3/salesorders*' => Http::response(['message' => 'boom'], 500)]);
    $order = orderFor(Company::factory()->approved()->create(['zoho_customer_id' => 'cust-1']));

    expect(fn () => app(PushOrderToZoho::class)->handle(new OrderPlaced($order)))
        ->toThrow(ZohoException::class);

    $order->refresh();
    expect($order->zoho_push_status)->toBe(ZohoPushStatus::Failed)
        ->and($order->zoho_push_error)->not->toBeNull();
});

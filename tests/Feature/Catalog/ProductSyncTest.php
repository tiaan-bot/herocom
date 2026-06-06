<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SyncProductsFromZoho;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
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
        'zoho.retry.max_attempts' => 4,
        'zoho.retry.base_backoff_ms' => 1,
    ]);

    ZohoToken::query()->create([
        'refresh_token' => 'r',
        'access_token' => 'valid',
        'access_token_expires_at' => now()->addHour(),
    ]);
});

function zohoItem(array $overrides = []): array
{
    return array_replace([
        'item_id' => '1001',
        'name' => 'Widget',
        'sku' => 'W-1',
        'rate' => 100.0,
        'stock_on_hand' => 10,
        'status' => 'active',
        'unit' => 'pcs',
        'last_modified_time' => '2026-06-01T10:00:00+0200',
    ], $overrides);
}

function sync(bool $full = true): void
{
    app(SyncProductsFromZoho::class)->execute($full);
}

it('upserts items idempotently — re-running changes nothing', function () {
    Http::fake(['*/books/v3/items*' => Http::sequence()
        ->push(['items' => [zohoItem(['rate' => 100])]])
        ->push(['items' => []])
        ->push(['items' => [zohoItem(['rate' => 100])]])
        ->push(['items' => []]),
    ]);

    sync();
    $first = Product::sole();

    sync();
    expect(Product::count())->toBe(1)
        ->and(Product::sole()->id)->toBe($first->id)
        ->and((float) Product::sole()->rate)->toBe(100.0);
});

it('marks products missing from a full sync as inactive', function () {
    Product::factory()->create(['zoho_item_id' => 'gone', 'status' => ProductStatus::Active]);

    Http::fake(['*/books/v3/items*' => Http::sequence()
        ->push(['items' => [zohoItem(['item_id' => '1001'])]])
        ->push(['items' => []]),
    ]);

    sync(full: true);

    expect(Product::where('zoho_item_id', 'gone')->sole()->status)->toBe(ProductStatus::Inactive)
        ->and(Product::where('zoho_item_id', '1001')->sole()->status)->toBe(ProductStatus::Active);
});

it('walks all pages', function () {
    Http::fake(['*/books/v3/items*' => Http::sequence()
        ->push(['items' => [zohoItem(['item_id' => '1']), zohoItem(['item_id' => '2'])]])
        ->push(['items' => [zohoItem(['item_id' => '3'])]])
        ->push(['items' => []]),
    ]);

    app(SyncProductsFromZoho::class)->execute(full: true);

    expect(Product::count())->toBe(3);
});

it('retries on 429 then syncs', function () {
    Sleep::fake();

    Http::fake(['*/books/v3/items*' => Http::sequence()
        ->push('', 429, ['Retry-After' => '1'])
        ->push(['items' => [zohoItem(['item_id' => '1'])]])
        ->push(['items' => []]),
    ]);

    sync(full: true);

    expect(Product::count())->toBe(1);
});

it('maps Zoho fields and marks inactive items', function () {
    Http::fake(['*/books/v3/items*' => Http::sequence()
        ->push(['items' => [zohoItem(['item_id' => '9', 'name' => 'Dead SKU', 'status' => 'inactive', 'rate' => 250.5])]])
        ->push(['items' => []]),
    ]);

    sync();

    $product = Product::where('zoho_item_id', '9')->sole();
    expect($product->name)->toBe('Dead SKU')
        ->and($product->status)->toBe(ProductStatus::Inactive)
        ->and((float) $product->rate)->toBe(250.5)
        ->and($product->uuid)->not->toBeNull();
});

it('zoho:sync-products --full reports a summary', function () {
    Http::fake(['*/books/v3/items*' => Http::sequence()
        ->push(['items' => [zohoItem(['item_id' => '1'])]])
        ->push(['items' => []]),
    ]);

    $this->artisan('zoho:sync-products', ['--full' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Synced 1');

    expect(Product::count())->toBe(1);
});

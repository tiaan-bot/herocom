<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SyncProductsFromZoho;
use App\Domain\Catalog\Models\Product;
use App\Domain\Shared\Zoho\Models\ZohoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

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

/**
 * Fake a single Zoho item across the list + detail endpoints. `$unticked` detail
 * has no image; the item is portal-visible so the write path runs in full.
 */
function fakeSyncItem(string $rate): void
{
    Http::fake([
        '*/books/v3/items/*' => Http::response(['item' => [
            'custom_field_hash' => ['cf_sync_to_portal_unformatted' => true],
        ]]),
        '*/books/v3/items?*' => Http::sequence()
            ->push(['items' => [['item_id' => '1001', 'name' => 'Widget', 'sku' => 'W-1', 'rate' => $rate, 'status' => 'active']]])
            ->push(['items' => []]),
    ]);
}

it('has dropped image_url and added the image storage columns on the migrated schema', function () {
    expect(Schema::hasColumn('products', 'image_url'))->toBeFalse()
        ->and(Schema::hasColumn('products', 'image_document_id'))->toBeTrue()
        ->and(Schema::hasColumn('products', 'image_path'))->toBeTrue()
        ->and(Schema::hasColumn('products', 'image_mime'))->toBeTrue();
});

it('persists a NEW product through the real sync write (INSERT) with no image_url column', function () {
    // Regression: the create path must not reference the dropped image_url column.
    // A stray image_url in the insert set raises SQLSTATE[42703] on Postgres and
    // "no column named image_url" on SQLite — either way this test would fail.
    fakeSyncItem('100');

    app(SyncProductsFromZoho::class)->execute(true);

    $product = Product::where('zoho_item_id', '1001')->sole();
    expect((float) $product->rate)->toBe(100.0)
        ->and($product->sync_to_portal)->toBeTrue();
});

it('persists an EXISTING product through the real sync write (UPDATE) with no image_url column', function () {
    // Seed a row, then sync the same zoho_item_id so updateOrCreate takes the
    // UPDATE branch — the path that emits "UPDATE products SET ..." on prod.
    Product::factory()->create(['zoho_item_id' => '1001', 'rate' => 1]);

    fakeSyncItem('250.50');

    app(SyncProductsFromZoho::class)->execute(true);

    expect(Product::where('zoho_item_id', '1001')->count())->toBe(1)
        ->and((float) Product::where('zoho_item_id', '1001')->sole()->rate)->toBe(250.5);
});

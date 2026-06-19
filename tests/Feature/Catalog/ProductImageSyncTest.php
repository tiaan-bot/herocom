<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SyncProductsFromZoho;
use App\Domain\Catalog\Models\Product;
use App\Domain\Shared\Zoho\Models\ZohoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    Storage::fake('r2_catalog');
});

/**
 * Fake the three item endpoints for a single item (id 1001): the raw-bytes image
 * endpoint (matched first, before the detail wildcard), the JSON detail endpoint,
 * and the list sequence. `$ticked` drives cf_sync_to_portal; `$imageDocumentId`
 * (when non-null, may be an int as Zoho actually sends it) is placed on the detail;
 * `$image` overrides the image response; `$extraDetail` merges extra detail keys
 * (e.g. image_name / image_type / documents).
 *
 * @param  array<string, mixed>  $extraDetail
 */
function fakeCatalogItem(bool $ticked, mixed $imageDocumentId = null, mixed $image = null, array $extraDetail = []): void
{
    $detail = ['custom_field_hash' => $ticked ? ['cf_sync_to_portal_unformatted' => true] : []];
    if ($imageDocumentId !== null) {
        $detail['image_document_id'] = $imageDocumentId;
    }
    $detail = array_merge($detail, $extraDetail);

    Http::fake([
        '*/books/v3/items/*/image*' => $image ?? Http::response('PNG-BYTES', 200, ['Content-Type' => 'image/png']),
        '*/books/v3/items/*' => Http::response(['item' => $detail]),
        '*/books/v3/items?*' => Http::sequence()
            ->push(['items' => [['item_id' => '1001', 'name' => 'Widget', 'sku' => 'W-1', 'rate' => 100, 'status' => 'active']]])
            ->push(['items' => []]),
    ]);
}

function runSync(): void
{
    app(SyncProductsFromZoho::class)->execute(true);
}

function assertImageNotFetched(): void
{
    Http::assertNotSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/image'));
}

it('stores the image when ticked and the document id is new', function () {
    fakeCatalogItem(ticked: true, imageDocumentId: 'doc-1');

    runSync();

    $product = Product::where('zoho_item_id', '1001')->sole();

    expect($product->image_document_id)->toBe('doc-1')
        ->and($product->image_mime)->toBe('image/png')
        ->and($product->image_path)->toBe("products/{$product->id}.png");

    Storage::disk('r2_catalog')->assertExists("products/{$product->id}.png");
    expect(Storage::disk('r2_catalog')->get("products/{$product->id}.png"))->toBe('PNG-BYTES');
});

it('stores the image when Zoho sends image_document_id as a JSON number', function () {
    // Regression: Zoho returns image_document_id as an integer. A string-only check
    // discarded it → the fetch was never attempted and the image stayed null.
    fakeCatalogItem(ticked: true, imageDocumentId: 460000000012345);

    runSync();

    $product = Product::where('zoho_item_id', '1001')->sole();

    expect($product->image_document_id)->toBe('460000000012345')
        ->and($product->image_path)->toBe("products/{$product->id}.png");

    Storage::disk('r2_catalog')->assertExists("products/{$product->id}.png");
});

it('stores the image when Zoho exposes it only via image_name/image_type', function () {
    // Some Books item payloads carry no image_document_id, only image_name + type.
    fakeCatalogItem(ticked: true, imageDocumentId: null, extraDetail: [
        'image_name' => 'mikrotik-hp5.png',
        'image_type' => 'png',
    ]);

    runSync();

    $product = Product::where('zoho_item_id', '1001')->sole();

    expect($product->image_document_id)->toStartWith('name-')
        ->and($product->image_path)->toBe("products/{$product->id}.png");

    Storage::disk('r2_catalog')->assertExists("products/{$product->id}.png");
});

it('logs a warning when Zoho reports an image but the fetch returns nothing', function () {
    Log::spy();

    // Detail advertises an image, but the image endpoint yields no usable bytes.
    fakeCatalogItem(ticked: true, imageDocumentId: 'doc-1', image: Http::response('', 200, ['Content-Type' => 'image/png']));

    runSync();

    $product = Product::where('zoho_item_id', '1001')->sole();
    expect($product->image_path)->toBeNull();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'fetch returned nothing'))
        ->once();
});

it('does NOT fetch the image when the document id is unchanged', function () {
    $existing = Product::factory()->withImage('doc-1')->create(['zoho_item_id' => '1001']);
    Storage::disk('r2_catalog')->put($existing->image_path, 'OLD-BYTES');

    fakeCatalogItem(ticked: true, imageDocumentId: 'doc-1');

    runSync();

    // The image endpoint must never be hit — that is what keeps the 30-min
    // incremental + nightly full sync inside Zoho's 5,000/day cap.
    assertImageNotFetched();

    $existing->refresh();
    expect($existing->image_document_id)->toBe('doc-1')
        ->and(Storage::disk('r2_catalog')->get($existing->image_path))->toBe('OLD-BYTES');
});

it('clears the image when it is removed in Zoho', function () {
    $existing = Product::factory()->withImage('doc-1')->create(['zoho_item_id' => '1001']);
    Storage::disk('r2_catalog')->put($existing->image_path, 'OLD-BYTES');
    $oldPath = $existing->image_path;

    // Ticked, but the detail no longer carries an image_document_id.
    fakeCatalogItem(ticked: true, imageDocumentId: null);

    runSync();

    assertImageNotFetched();

    $existing->refresh();
    expect($existing->image_document_id)->toBeNull()
        ->and($existing->image_path)->toBeNull()
        ->and($existing->image_mime)->toBeNull();

    Storage::disk('r2_catalog')->assertMissing($oldPath);
});

it('never fetches an image for products not ticked for the portal', function () {
    // Unticked → image handling is skipped entirely, even with a document id present.
    fakeCatalogItem(ticked: false, imageDocumentId: 'doc-1');

    runSync();

    assertImageNotFetched();

    $product = Product::where('zoho_item_id', '1001')->sole();
    expect($product->sync_to_portal)->toBeFalse()
        ->and($product->image_path)->toBeNull()
        ->and($product->image_document_id)->toBeNull();
});

it('does not fail the sync when an image fetch errors', function () {
    config(['zoho.retry.max_attempts' => 1]); // a 5xx exhausts retries immediately → throws

    fakeCatalogItem(ticked: true, imageDocumentId: 'doc-1', image: Http::response('boom', 500));

    runSync();

    // Product is still upserted; the failed image just stays empty.
    $product = Product::where('zoho_item_id', '1001')->sole();
    expect($product->sync_to_portal)->toBeTrue()
        ->and($product->image_path)->toBeNull()
        ->and($product->image_document_id)->toBeNull();
});

// --- public serving route -------------------------------------------------

it('serves the image bytes with an immutable cache header for a ticked product', function () {
    $product = Product::factory()->withImage('doc-1')->create();
    Storage::disk('r2_catalog')->put($product->image_path, 'PNG-BYTES');

    $response = $this->get(route('catalog.image', $product));

    $response->assertOk()->assertHeader('Content-Type', 'image/png');

    // Symfony normalises Cache-Control directive order, so assert by directive.
    $cacheControl = (string) $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('public')
        ->toContain('max-age=31536000')
        ->toContain('immutable');

    expect($response->streamedContent())->toBe('PNG-BYTES');
});

it('404s the image route for a product not synced to portal', function () {
    $product = Product::factory()->hiddenFromPortal()->withImage('doc-1')->create();
    Storage::disk('r2_catalog')->put($product->image_path, 'PNG-BYTES');

    $this->get(route('catalog.image', $product))->assertNotFound();
});

it('404s the image route for a ticked product with no stored image', function () {
    $product = Product::factory()->create(); // sync_to_portal true, no image

    $this->get(route('catalog.image', $product))->assertNotFound();
});

it('404s the image route when the stored object is missing from the disk', function () {
    $product = Product::factory()->withImage('doc-1')->create(); // path set, nothing on disk

    $this->get(route('catalog.image', $product))->assertNotFound();
});

it('exposes the cache-busted image_url accessor only when visible with a stored image', function () {
    $withImage = Product::factory()->withImage('doc-9')->create();
    $hidden = Product::factory()->hiddenFromPortal()->withImage('doc-9')->create();
    $noImage = Product::factory()->create();

    expect($withImage->image_url)->toBe(route('catalog.image', $withImage).'?v=doc-9')
        ->and($hidden->image_url)->toBeNull()
        ->and($noImage->image_url)->toBeNull();
});

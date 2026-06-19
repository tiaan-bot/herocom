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

it('stores the image from documents[]/image_name when there is no image_document_id (the live shape)', function () {
    // The real NET-CPE-MIK-HP5-001 payload: no image_document_id; image is in
    // image_name + documents[0] (file_type jpg). The image endpoint returns the
    // bytes with an octet-stream content-type, so the mime is derived from the type.
    fakeCatalogItem(
        ticked: true,
        imageDocumentId: null,
        image: Http::response('JPG-BYTES', 200, ['Content-Type' => 'application/octet-stream']),
        extraDetail: [
            'image_name' => 'RBLHG-5HPnD-XL4pack.jpg',
            'image_type' => 'jpg',
            'documents' => [
                ['document_id' => '7763961000000259290', 'file_name' => 'RBLHG-5HPnD-XL4pack.jpg', 'file_type' => 'jpg'],
            ],
        ],
    );

    runSync();

    $product = Product::where('zoho_item_id', '1001')->sole();

    expect($product->image_document_id)->toBe('7763961000000259290') // documents[0].document_id
        ->and($product->image_mime)->toBe('image/jpeg')               // jpg → image/jpeg
        ->and($product->image_path)->toBe("products/{$product->id}.jpg");

    Storage::disk('r2_catalog')->assertExists("products/{$product->id}.jpg");
    expect(Storage::disk('r2_catalog')->get("products/{$product->id}.jpg"))->toBe('JPG-BYTES');
});

it('syncs the image for a pre-existing product (runs on updates, not only creates)', function () {
    // Mirrors NET-CPE-MIK-HP5-001: the product pre-existed its image. The image
    // path must run on the update, not be confined to first-time creates.
    Product::factory()->create([
        'zoho_item_id' => '1001',
        'image_document_id' => null,
        'image_path' => null,
        'image_mime' => null,
    ]);

    fakeCatalogItem(
        ticked: true,
        imageDocumentId: null,
        image: Http::response('JPG-BYTES', 200, ['Content-Type' => 'application/octet-stream']),
        extraDetail: [
            'image_name' => 'RBLHG-5HPnD-XL4pack.jpg',
            'image_type' => 'jpg',
            'documents' => [
                ['document_id' => '7763961000000259290', 'file_name' => 'RBLHG-5HPnD-XL4pack.jpg', 'file_type' => 'jpg'],
            ],
        ],
    );

    runSync();

    $product = Product::where('zoho_item_id', '1001')->sole();

    expect($product->image_document_id)->toBe('7763961000000259290')
        ->and($product->image_mime)->toBe('image/jpeg')
        ->and($product->image_path)->toBe("products/{$product->id}.jpg");

    Storage::disk('r2_catalog')->assertExists("products/{$product->id}.jpg");
});

it('resolves documents[0].document_id and enters the fetch for the real NET-CPE-MIK-HP5-001 payload', function () {
    // EXACT live inputs (item_id 7763961000000190002): no image_document_id; the
    // image is image_name + a single image-typed documents[] entry.
    Http::fake([
        '*/books/v3/items/7763961000000190002/image*' => Http::response('JPG-BYTES', 200, ['Content-Type' => 'application/octet-stream']),
        '*/books/v3/items/*' => Http::response(['item' => [
            'item_id' => '7763961000000190002',
            'name' => 'NET-CPE-MIK-HP5-001',
            'sku' => 'NET-CPE-MIK-HP5-001',
            'custom_field_hash' => ['cf_sync_to_portal_unformatted' => true],
            'image_document_id' => null,
            'image_name' => 'RBLHG-5HPnD-XL4pack.jpg',
            'image_type' => 'jpg',
            'documents' => [
                ['document_id' => '7763961000000259290', 'file_name' => 'RBLHG-5HPnD-XL4pack.jpg', 'file_type' => 'jpg', 'attachment_order' => 1],
            ],
        ]]),
        '*/books/v3/items?*' => Http::sequence()
            ->push(['items' => [['item_id' => '7763961000000190002', 'name' => 'NET-CPE-MIK-HP5-001', 'sku' => 'NET-CPE-MIK-HP5-001', 'rate' => 100, 'status' => 'active']]])
            ->push(['items' => []]),
    ]);

    app(SyncProductsFromZoho::class)->execute(true);

    $product = Product::where('zoho_item_id', '7763961000000190002')->sole();

    // The key resolves to documents[0].document_id, and the fetch+store path is entered.
    expect($product->image_document_id)->toBe('7763961000000259290')
        ->and($product->image_mime)->toBe('image/jpeg')
        ->and($product->image_path)->toBe("products/{$product->id}.jpg");

    Http::assertSent(fn (ClientRequest $r): bool => str_contains($r->url(), '/items/7763961000000190002/image'));
    Storage::disk('r2_catalog')->assertExists("products/{$product->id}.jpg");
    expect(Storage::disk('r2_catalog')->get("products/{$product->id}.jpg"))->toBe('JPG-BYTES');
});

it('logs an unconditional syncImage entry line for every item (provable execution)', function () {
    Log::spy();

    // Even an UNticked item must produce the entry line — that proves the image
    // path is reached for every item, in both the full and incremental loops.
    fakeCatalogItem(ticked: false, imageDocumentId: null, extraDetail: [
        'image_name' => 'RBLHG-5HPnD-XL4pack.jpg',
        'image_type' => 'jpg',
    ]);

    runSync();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context = []): bool {
            return $message === 'syncImage entry' && ($context['item_id'] ?? null) === '1001';
        })
        ->atLeast()
        ->once();
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

<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\DataTransferObjects\ProductSyncResult;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Shared\Zoho\Models\ZohoSyncState;
use App\Domain\Shared\Zoho\ZohoClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * One-way sync of Zoho Books items into the local products mirror. Upserts by
 * the unique zoho_item_id (re-runs are idempotent on the data); a full sync
 * also marks any product no longer present in Zoho as inactive (never deletes).
 */
final class SyncProductsFromZoho
{
    /**
     * Zoho Books org timezone — the incremental cursor is expressed in this zone
     * with a numeric offset, matching how Zoho returns last_modified_time.
     */
    private const ORG_TIMEZONE = 'Africa/Johannesburg';

    /** Sync-state key for the products incremental cursor. */
    private const CURSOR_KEY = 'products';

    /**
     * Re-query a little before the cursor so a row written right on the boundary
     * (or in a clock-skew window) is never missed. The upsert is idempotent, so
     * re-processing the overlap is harmless.
     */
    private const LOOKBACK_MINUTES = 5;

    /**
     * Maps an image MIME type to the storage key extension.
     *
     * @var array<string, string>
     */
    private const IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'image/bmp' => 'bmp',
    ];

    public function __construct(
        private readonly ZohoClient $zoho,
        private readonly FilesystemFactory $filesystem,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(bool $full = false): ProductSyncResult
    {
        $now = CarbonImmutable::now();

        // Incremental runs are driven by an explicit cursor. With nothing stored
        // yet, fall back to a full sync rather than pulling the whole catalogue
        // under an empty/invalid filter.
        $cursor = $full ? null : $this->readCursor();
        if (! $full && $cursor === null) {
            $full = true;
        }

        $filters = $full ? [] : $this->incrementalFilters($cursor);

        // High-water mark of items actually processed this run (UTC). The stored
        // cursor is advanced to this ONLY after the changed set is fully
        // paginated below — if the loop throws or the worker times out first, the
        // advance never runs, so we never skip past unprocessed items.
        $highWater = $cursor;

        $seen = [];
        $synced = 0;
        $page = 1;

        do {
            $items = $this->zoho->listItems($page, $filters);

            foreach ($items as $item) {
                $zohoItemId = (string) ($item['item_id'] ?? '');
                if ($zohoItemId === '') {
                    continue;
                }

                // The list endpoint omits custom fields, so the "Sync to portal"
                // flag must be read from each item's detail. Zoho-owned: written
                // on every run (Zoho wins), absent key => false.
                $detail = $this->zoho->getItem($zohoItemId);

                $syncToPortal = $this->isSyncedToPortal($detail);

                $attributes = $this->attributes($item, $now);
                $attributes['sync_to_portal'] = $syncToPortal;

                $product = Product::query()->updateOrCreate(
                    ['zoho_item_id' => $zohoItemId],
                    $attributes,
                );

                // Image handling only for portal-visible products. A single image
                // failure logs and continues — it never fails the product or job.
                if ($syncToPortal) {
                    $this->syncImage($product, $detail, $zohoItemId);
                }

                $highWater = $this->advanceHighWater($highWater, $item);
                $seen[] = $zohoItemId;
                $synced++;
            }

            $page++;
        } while ($items !== []);

        $deactivated = 0;
        if ($full) {
            $deactivated = Product::query()
                ->whereNotIn('zoho_item_id', $seen)
                ->where('status', ProductStatus::Active)
                ->update(['status' => ProductStatus::Inactive, 'last_synced_at' => $now]);
        }

        // Reached only after a clean, complete pagination of the changed set.
        $this->writeCursor($highWater);

        return new ProductSyncResult($synced, $deactivated);
    }

    /**
     * Read the Zoho "Sync to portal" checkbox from an item's detail payload.
     * Truthy only when the unformatted (boolean) custom field is true — the plain
     * custom_field_hash.cf_sync_to_portal is the string "true" and must not be
     * compared. Absent key => false.
     *
     * @param  array<string, mixed>  $detail
     */
    private function isSyncedToPortal(array $detail): bool
    {
        $hash = $detail['custom_field_hash'] ?? [];
        if (is_array($hash) && array_key_exists('cf_sync_to_portal_unformatted', $hash)) {
            return $hash['cf_sync_to_portal_unformatted'] === true;
        }

        // Fallback: the custom_fields[] entry carries a boolean `value`.
        foreach ($detail['custom_fields'] ?? [] as $field) {
            if (is_array($field) && ($field['api_name'] ?? null) === 'cf_sync_to_portal') {
                return ($field['value'] ?? null) === true;
            }
        }

        return false;
    }

    /**
     * Reconcile the product's stored image with Zoho's, gated on the image's
     * identity key:
     *  - Zoho has an image whose key differs (or we hold no file yet) → fetch + store.
     *  - Zoho has no image but we held one → delete the object + null the columns.
     *  - key unchanged → do nothing (no fetch — keeps us within Zoho's daily cap).
     *
     * Every skip and failure is logged: a mis-read of Zoho's image keys must never
     * be silent again.
     *
     * @param  array<string, mixed>  $detail
     */
    private function syncImage(Product $product, array $detail, string $zohoItemId): void
    {
        $imageKey = $this->zohoImageKey($detail);

        try {
            if ($imageKey !== null) {
                if ($imageKey === $product->image_document_id && $product->image_path !== null) {
                    return; // Unchanged — already stored.
                }

                $image = $this->zoho->fetchItemImage($zohoItemId);
                if ($image === null) {
                    $this->logger->warning('Zoho product image: detail reports an image but the fetch returned nothing.', [
                        'zoho_item_id' => $zohoItemId,
                        'image_key' => $imageKey,
                        'image_fields' => $this->imageFields($detail),
                    ]);

                    return;
                }

                $disk = $this->disk();
                $path = "products/{$product->id}.".$this->extensionFor($image['mime']);

                // Replace any previous file whose key differs (e.g. format changed).
                if ($product->image_path !== null && $product->image_path !== $path) {
                    $disk->delete($product->image_path);
                }

                $disk->put($path, $image['contents']);

                $product->forceFill([
                    'image_path' => $path,
                    'image_document_id' => $imageKey,
                    'image_mime' => $image['mime'],
                ])->save();

                return;
            }

            // Image removed in Zoho — drop ours too.
            if ($product->image_path !== null || $product->image_document_id !== null) {
                if ($product->image_path !== null) {
                    $this->disk()->delete($product->image_path);
                }

                $product->forceFill([
                    'image_path' => null,
                    'image_document_id' => null,
                    'image_mime' => null,
                ])->save();

                return;
            }

            // No image either side. Log the raw image fields so a mis-read of Zoho's
            // keys (a numeric id, an image_name-only payload, …) is never invisible.
            $this->logger->debug('Zoho product image: no image detected on a portal item.', [
                'zoho_item_id' => $zohoItemId,
                'image_fields' => $this->imageFields($detail),
            ]);
        } catch (Throwable $e) {
            $this->logger->warning('Zoho product image sync failed.', [
                'zoho_item_id' => $zohoItemId,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve a stable identity key for the item's image from whatever Zoho Books
     * actually returns. The detail may carry the id as image_document_id (commonly
     * a JSON *number* — it must not be discarded by a string-only check), expose the
     * image only via image_name (+ image_type), or list it under documents[].
     * Returns null when the item has no image.
     *
     * @param  array<string, mixed>  $detail
     */
    private function zohoImageKey(array $detail): ?string
    {
        $documentId = $detail['image_document_id'] ?? null;
        if (is_scalar($documentId)) {
            $documentId = (string) $documentId;
            if ($documentId !== '' && $documentId !== '0') {
                return $documentId;
            }
        }

        $imageName = $detail['image_name'] ?? null;
        if (is_scalar($imageName) && (string) $imageName !== '') {
            $type = is_scalar($detail['image_type'] ?? null) ? (string) $detail['image_type'] : '';

            // No document id — key on a hash of name+type (URL-safe; the accessor
            // appends it as ?v=…). A changed name or type re-fetches.
            return 'name-'.substr(md5((string) $imageName.'|'.$type), 0, 16);
        }

        $documents = $detail['documents'] ?? null;
        if (is_array($documents) && isset($documents[0]) && is_array($documents[0])) {
            $docId = $documents[0]['document_id'] ?? null;
            if (is_scalar($docId) && (string) $docId !== '') {
                return (string) $docId;
            }
        }

        return null;
    }

    /**
     * The image-related fields Zoho returned, for diagnostic logging.
     *
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function imageFields(array $detail): array
    {
        return [
            'image_document_id' => $detail['image_document_id'] ?? null,
            'image_name' => $detail['image_name'] ?? null,
            'image_type' => $detail['image_type'] ?? null,
            'has_documents' => ! empty($detail['documents']),
        ];
    }

    private function disk(): Filesystem
    {
        return $this->filesystem->disk((string) $this->config->get('catalog.image_disk', 'r2_catalog'));
    }

    private function extensionFor(string $mime): string
    {
        return self::IMAGE_EXTENSIONS[strtolower($mime)] ?? 'jpg';
    }

    /**
     * Build the incremental `last_modified_time` filter from the stored cursor,
     * less a short lookback overlap. Zoho Books wants ISO 8601 with a numeric
     * timezone offset in the org timezone (e.g. 2026-06-19T14:30:00+0200); the
     * bare `Y-m-d H:i:s` and the colon-offset variant are both rejected (HTTP 400).
     *
     * The cursor is a true UTC instant; it is expressed in Africa/Johannesburg so
     * the value matches how Zoho returns last_modified_time for this org.
     *
     * @return array<string, string>
     */
    private function incrementalFilters(CarbonImmutable $cursor): array
    {
        $lastModifiedTime = $cursor
            ->subMinutes(self::LOOKBACK_MINUTES)
            ->setTimezone(self::ORG_TIMEZONE)
            ->format('Y-m-d\TH:i:sO');

        // Surface the exact value sent — the test suite fakes Zoho, so the real
        // format can only be confirmed from the live incremental run's logs.
        $this->logger->debug('Zoho incremental product sync: last_modified_time filter.', [
            'last_modified_time' => $lastModifiedTime,
        ]);

        return ['last_modified_time' => $lastModifiedTime];
    }

    /**
     * Read the stored products cursor as a true UTC instant, or null if unset.
     */
    private function readCursor(): ?CarbonImmutable
    {
        $state = ZohoSyncState::query()->where('key', self::CURSOR_KEY)->first();

        return $state?->last_modified_cursor !== null
            ? CarbonImmutable::instance($state->last_modified_cursor)->utc()
            : null;
    }

    /**
     * Advance the cursor to the high-water mark of this run. No-op when nothing
     * new was processed (keeps the cursor where it was).
     */
    private function writeCursor(?CarbonImmutable $highWater): void
    {
        if ($highWater === null) {
            return;
        }

        ZohoSyncState::query()->updateOrCreate(
            ['key' => self::CURSOR_KEY],
            ['last_modified_cursor' => $highWater->utc()],
        );
    }

    /**
     * Track the latest last_modified_time seen among processed items (UTC).
     *
     * @param  array<string, mixed>  $item
     */
    private function advanceHighWater(?CarbonImmutable $highWater, array $item): ?CarbonImmutable
    {
        if (! isset($item['last_modified_time'])) {
            return $highWater;
        }

        $itemModified = CarbonImmutable::parse((string) $item['last_modified_time'])->utc();

        return $highWater === null || $itemModified->greaterThan($highWater)
            ? $itemModified
            : $highWater;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function attributes(array $item, CarbonImmutable $now): array
    {
        return [
            'sku' => $item['sku'] ?? null,
            'name' => (string) ($item['name'] ?? ''),
            'description' => $item['description'] ?? null,
            'rate' => $item['rate'] ?? 0,
            'rate_currency' => $item['currency_code'] ?? 'ZAR',
            'stock_on_hand' => $item['stock_on_hand'] ?? ($item['available_stock'] ?? 0),
            'unit' => $item['unit'] ?? null,
            'brand' => $item['brand'] ?? null,
            'category' => $item['category_name'] ?? ($item['group_name'] ?? null),
            'status' => ($item['status'] ?? 'active') === 'active' ? ProductStatus::Active : ProductStatus::Inactive,
            // Store the true UTC instant. Zoho sends +02:00 timestamps; without the
            // explicit ->utc() Eloquent persists the SAST wall-clock and reads it
            // back as UTC (~2h ahead), which then poisons the incremental cursor.
            'zoho_last_modified_at' => isset($item['last_modified_time'])
                ? CarbonImmutable::parse((string) $item['last_modified_time'])->utc()
                : null,
            'last_synced_at' => $now,
        ];
    }
}

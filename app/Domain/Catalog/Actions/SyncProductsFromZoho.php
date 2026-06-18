<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\DataTransferObjects\ProductSyncResult;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
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
        $filters = $full ? [] : $this->incrementalFilter();

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
     * Reconcile the product's stored image with Zoho's, gated on the image
     * document id as the change-detection key:
     *  - Zoho has an image whose id differs (or we hold no file yet) → fetch + store.
     *  - Zoho has no image but we held one → delete the object + null the columns.
     *  - id unchanged → do nothing (no fetch — keeps us within Zoho's daily cap).
     * Any failure is logged and swallowed so one image never fails the sync.
     *
     * @param  array<string, mixed>  $detail
     */
    private function syncImage(Product $product, array $detail, string $zohoItemId): void
    {
        $documentId = $detail['image_document_id'] ?? null;
        $documentId = is_string($documentId) && $documentId !== '' ? $documentId : null;

        try {
            if ($documentId !== null) {
                $unchanged = $documentId === $product->image_document_id && $product->image_path !== null;
                if ($unchanged) {
                    return;
                }

                $image = $this->zoho->fetchItemImage($zohoItemId);
                if ($image === null) {
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
                    'image_document_id' => $documentId,
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
            }
        } catch (Throwable $e) {
            $this->logger->warning('Zoho product image sync failed.', [
                'zoho_item_id' => $zohoItemId,
                'exception' => $e->getMessage(),
            ]);
        }
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
     * @return array<string, string>
     */
    private function incrementalFilter(): array
    {
        $since = Product::query()->max('zoho_last_modified_at');

        if ($since === null) {
            return [];
        }

        return ['last_modified_time' => CarbonImmutable::parse($since)->toIso8601String()];
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
            'zoho_last_modified_at' => isset($item['last_modified_time'])
                ? CarbonImmutable::parse((string) $item['last_modified_time'])
                : null,
            'last_synced_at' => $now,
        ];
    }
}

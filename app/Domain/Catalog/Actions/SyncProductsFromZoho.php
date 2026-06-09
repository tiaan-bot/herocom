<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\DataTransferObjects\ProductSyncResult;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Shared\Zoho\ZohoClient;
use Carbon\CarbonImmutable;

/**
 * One-way sync of Zoho Books items into the local products mirror. Upserts by
 * the unique zoho_item_id (re-runs are idempotent on the data); a full sync
 * also marks any product no longer present in Zoho as inactive (never deletes).
 */
final class SyncProductsFromZoho
{
    public function __construct(
        private readonly ZohoClient $zoho,
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

                $attributes = $this->attributes($item, $now);
                $attributes['sync_to_portal'] = $this->isSyncedToPortal($detail);

                Product::query()->updateOrCreate(
                    ['zoho_item_id' => $zohoItemId],
                    $attributes,
                );

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
            'image_url' => $item['image_url'] ?? null,
            'status' => ($item['status'] ?? 'active') === 'active' ? ProductStatus::Active : ProductStatus::Inactive,
            'zoho_last_modified_at' => isset($item['last_modified_time'])
                ? CarbonImmutable::parse((string) $item['last_modified_time'])
                : null,
            'last_synced_at' => $now,
        ];
    }
}

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

                Product::query()->updateOrCreate(
                    ['zoho_item_id' => $zohoItemId],
                    $this->attributes($item, $now),
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

<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Shared\Concerns\HasUuid;
use Database\Factories\Catalog\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $uuid
 * @property string $zoho_item_id
 * @property string|null $sku
 * @property string $name
 * @property string $rate
 * @property string $rate_currency
 * @property string $stock_on_hand
 * @property string|null $brand
 * @property string|null $category
 * @property ProductStatus $status
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasUuid;

    protected $fillable = [
        'zoho_item_id',
        'sku',
        'name',
        'description',
        'rate',
        'rate_currency',
        'stock_on_hand',
        'unit',
        'brand',
        'category',
        'image_url',
        'status',
        'zoho_last_modified_at',
        'last_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'stock_on_hand' => 'decimal:2',
            'status' => ProductStatus::class,
            'zoho_last_modified_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ProductStatus::Active);
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}

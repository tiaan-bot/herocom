<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Shared\Concerns\HasUuid;
use Database\Factories\Catalog\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property string|null $image_document_id
 * @property string|null $image_path
 * @property string|null $image_mime
 * @property ProductStatus $status
 * @property bool $is_featured
 * @property bool $sync_to_portal
 * @property-read string|null $image_url
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
        'image_document_id',
        'image_path',
        'image_mime',
        'status',
        'is_featured',
        'sync_to_portal',
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
            'is_featured' => 'boolean',
            'sync_to_portal' => 'boolean',
            'zoho_last_modified_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Public, cache-busting URL for the product image, or null when there is no
     * stored image or the product is hidden from the portal. The `?v=` query is
     * the Zoho document id, so the immutable cached URL changes when the image
     * does. Returns the gated `catalog.image` route, never a bucket URL.
     *
     * @return Attribute<non-falsy-string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->image_path === null || ! $this->sync_to_portal) {
                return null;
            }

            return route('catalog.image', $this).'?v='.$this->image_document_id;
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ProductStatus::Active);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}

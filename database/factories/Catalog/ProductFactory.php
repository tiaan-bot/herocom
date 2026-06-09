<?php

declare(strict_types=1);

namespace Database\Factories\Catalog;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zoho_item_id' => (string) $this->faker->unique()->numerify('############'),
            'sku' => strtoupper($this->faker->bothify('SKU-####')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'rate' => $this->faker->randomFloat(2, 50, 5000),
            'rate_currency' => 'ZAR',
            'stock_on_hand' => $this->faker->numberBetween(0, 200),
            'unit' => 'pcs',
            'brand' => $this->faker->randomElement(['Acme', 'Globex', 'Initech', null]),
            'category' => $this->faker->randomElement(['Networking', 'Storage', 'Peripherals', null]),
            'status' => ProductStatus::Active,
            // Test fixtures are portal-visible by default (the DB column default is
            // false; production stays hidden until a sync confirms the Zoho tick).
            'sync_to_portal' => true,
            'zoho_last_modified_at' => now(),
            'last_synced_at' => now(),
        ];
    }

    public function hiddenFromPortal(): static
    {
        return $this->state(fn (): array => ['sync_to_portal' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => ProductStatus::Inactive]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => ['stock_on_hand' => 0]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}

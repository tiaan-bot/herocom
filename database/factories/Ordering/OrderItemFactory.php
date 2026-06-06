<?php

declare(strict_types=1);

namespace Database\Factories\Ordering;

use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->bothify('SKU-####')),
            'name' => fake()->words(3, true),
            'quantity' => 1,
            'unit_price_list' => 100,
            'unit_price' => 90,
            'currency' => 'ZAR',
            'line_total_ex_vat' => 90,
            'zoho_item_id' => (string) fake()->unique()->numerify('############'),
        ];
    }
}

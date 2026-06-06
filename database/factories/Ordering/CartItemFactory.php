<?php

declare(strict_types=1);

namespace Database\Factories\Ordering;

use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Models\Cart;
use App\Domain\Ordering\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'product_id' => Product::factory(),
            'quantity' => 1,
        ];
    }
}

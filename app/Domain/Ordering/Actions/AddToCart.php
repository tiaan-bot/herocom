<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Models\CartItem;
use App\Models\User;

final class AddToCart
{
    public function __construct(
        private readonly GetOrCreateOpenCart $carts,
    ) {}

    public function execute(User $user, Product $product, float $quantity = 1): CartItem
    {
        $cart = $this->carts->execute($user);

        $item = $cart->items()->where('product_id', $product->getKey())->first();

        if ($item !== null) {
            $item->increment('quantity', $quantity);

            return $item;
        }

        return $cart->items()->create([
            'product_id' => $product->getKey(),
            'quantity' => $quantity,
        ]);
    }
}

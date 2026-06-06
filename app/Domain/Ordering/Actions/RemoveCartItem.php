<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Models\CartItem;

final class RemoveCartItem
{
    public function execute(CartItem $item): void
    {
        $item->delete();
    }
}

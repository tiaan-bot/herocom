<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Enums\CartStatus;
use App\Domain\Ordering\Models\Cart;
use App\Models\User;

final class GetOrCreateOpenCart
{
    public function execute(User $user): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $user->getKey(), 'status' => CartStatus::Open],
            ['company_id' => $user->company_id],
        );
    }
}

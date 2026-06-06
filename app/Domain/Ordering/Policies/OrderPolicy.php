<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Policies;

use App\Domain\Ordering\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_orders');
    }

    public function view(User $user, Order $order): bool
    {
        if (! $user->can('view_orders')) {
            return false;
        }

        // Internal staff (no company) see all orders; resellers see only their company's.
        return $user->company_id === null || $user->company_id === $order->company_id;
    }

    public function accept(User $user, Order $order): bool
    {
        return $user->can('manage_orders');
    }

    public function reject(User $user, Order $order): bool
    {
        return $user->can('manage_orders');
    }
}

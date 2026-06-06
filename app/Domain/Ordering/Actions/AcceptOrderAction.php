<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Exceptions\OrderException;
use App\Domain\Ordering\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Access\Gate;

final class AcceptOrderAction
{
    public function __construct(
        private readonly Gate $gate,
    ) {}

    public function execute(Order $order, User $actor): Order
    {
        $this->gate->forUser($actor)->authorize('accept', $order);

        if ($order->status !== OrderStatus::Placed) {
            throw OrderException::notDecidable($order);
        }

        $order->update([
            'status' => OrderStatus::Accepted,
            'accepted_at' => CarbonImmutable::now(),
            'accepted_by' => $actor->getKey(),
        ]);

        $order->placedBy?->notify(new OrderStatusNotification($order));

        return $order;
    }
}

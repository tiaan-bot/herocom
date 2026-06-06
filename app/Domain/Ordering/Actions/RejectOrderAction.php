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

final class RejectOrderAction
{
    public function __construct(
        private readonly Gate $gate,
    ) {}

    public function execute(Order $order, User $actor, string $reason): Order
    {
        $this->gate->forUser($actor)->authorize('reject', $order);

        if ($order->status !== OrderStatus::Placed) {
            throw OrderException::notDecidable($order);
        }

        $order->update([
            'status' => OrderStatus::Rejected,
            'rejected_at' => CarbonImmutable::now(),
            'rejected_by' => $actor->getKey(),
            'rejection_reason' => $reason,
        ]);

        $order->placedBy?->notify(new OrderStatusNotification($order));

        return $order;
    }
}

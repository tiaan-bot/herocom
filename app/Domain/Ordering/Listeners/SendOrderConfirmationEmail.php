<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Listeners;

use App\Domain\Ordering\Events\OrderPlaced;
use App\Notifications\OrderConfirmationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class SendOrderConfirmationEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300, 900, 3600];

    public function handle(OrderPlaced $event): void
    {
        $placer = $event->order->placedBy;

        $placer?->notify(new OrderConfirmationNotification($event->order));
    }
}

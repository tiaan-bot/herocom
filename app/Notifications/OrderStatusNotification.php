<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $accepted = $this->order->status === OrderStatus::Accepted;

        $message = (new MailMessage)
            ->subject("Order {$this->order->order_number} ".($accepted ? 'accepted' : 'declined'))
            ->greeting("Order {$this->order->order_number}");

        if ($accepted) {
            return $message
                ->line('Good news — we\'ve accepted your order and it\'s being processed.')
                ->salutation('— The Herocom Distribution team');
        }

        return $message
            ->line('Unfortunately we\'re unable to fulfil this order.')
            ->lineIf((bool) $this->order->rejection_reason, "Reason: {$this->order->rejection_reason}")
            ->line('Please get in touch if you have any questions.')
            ->salutation('— The Herocom Distribution team');
    }
}

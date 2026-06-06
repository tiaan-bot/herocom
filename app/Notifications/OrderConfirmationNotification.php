<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Ordering\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmationNotification extends Notification implements ShouldQueue
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
        $order = $this->order->loadMissing('items');

        $message = (new MailMessage)
            ->subject("Order {$order->order_number} received — Herocom Distribution")
            ->greeting('Thank you for your order')
            ->line("We've received order {$order->order_number}. Our team will review and confirm it shortly.");

        foreach ($order->items as $item) {
            $message->line("• {$item->quantity} × {$item->name} — {$order->currency} ".number_format((float) $item->line_total_ex_vat, 2));
        }

        return $message
            ->line("Subtotal (ex VAT): {$order->currency} ".number_format((float) $order->subtotal_ex_vat, 2))
            ->salutation('— The Herocom Distribution team');
    }
}

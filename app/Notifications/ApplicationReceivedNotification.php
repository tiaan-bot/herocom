<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirmation sent to the applicant on submission ("we've received it").
 * Sent as an on-demand notification — the applicant is not yet a User.
 */
class ApplicationReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $contactName,
        private readonly string $companyName,
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
        return (new MailMessage)
            ->subject('We\'ve received your Herocom application')
            ->greeting("Hi {$this->contactName},")
            ->line("Thank you — we've received the reseller application for {$this->companyName}.")
            ->line('Our team will review it and be in touch. If we need anything further, we\'ll contact you at this address.')
            ->salutation('— The Herocom Distribution team');
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class OnboardingWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        // Signed, 7-day set-password link bound to the user's uuid.
        $url = URL::temporarySignedRoute('password.set', Carbon::now()->addDays(7), ['user' => $notifiable->uuid]);

        return (new MailMessage)
            ->subject('Welcome to Herocom Distribution')
            ->greeting("Welcome, {$notifiable->name}")
            ->line('Your reseller account has been approved.')
            ->line('Set your password to get started.')
            ->action('Set your password', $url)
            ->line('This link expires in 7 days. If it lapses, you can request a new one from the login page.');
    }
}

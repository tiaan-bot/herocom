<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

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
        $token = Password::broker()->createToken($notifiable);

        // TODO(portal-auth): point at the named reseller-portal set-password route once
        // Fortify/the portal auth flow exists. For now build the link from APP_URL.
        $url = url('/set-password?'.http_build_query([
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]));

        return (new MailMessage)
            ->subject('Welcome to Herocom Distribution')
            ->greeting("Welcome, {$notifiable->name}")
            ->line('Your reseller account has been approved.')
            ->line('Set your password to get started.')
            ->action('Set your password', $url)
            ->line('This link will expire shortly for your security.');
    }
}

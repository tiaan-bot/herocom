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

/**
 * Invite for an internal staff member to activate their account by setting a
 * password. Reuses the signed, single-use set-password route (guarded by
 * password_set_at) — the same mechanism as the reseller welcome flow.
 */
class StaffInviteNotification extends Notification implements ShouldQueue
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
        $url = URL::temporarySignedRoute('password.set', Carbon::now()->addDays(7), ['user' => $notifiable->uuid]);

        return (new MailMessage)
            ->subject('Activate your Herocom Distribution staff account')
            ->greeting("Hi {$notifiable->name},")
            ->line('An internal Herocom Distribution account has been created for you.')
            ->line('Set your password to activate your account and sign in.')
            ->action('Set your password', $url)
            ->line('This link expires in 7 days. If it lapses, you can request a new one from the login page.');
    }
}

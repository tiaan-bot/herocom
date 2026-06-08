<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

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
        private readonly ?string $pdfDisk = null,
        private readonly ?string $pdfPath = null,
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
        $mail = (new MailMessage)
            ->subject('We\'ve received your Herocom application')
            ->greeting("Hi {$this->contactName},")
            ->line("Thank you — we've received the reseller application for {$this->companyName}.")
            ->line('Our team will review it and be in touch. If we need anything further, we\'ll contact you at this address.');

        // A copy of the signed application form is attached when the PDF is ready.
        if ($this->pdfDisk !== null && $this->pdfPath !== null && Storage::disk($this->pdfDisk)->exists($this->pdfPath)) {
            $mail->line('A copy of your signed application form is attached for your records.')
                ->attachData(
                    Storage::disk($this->pdfDisk)->get($this->pdfPath) ?? '',
                    'Herocom-Application.pdf',
                    ['mime' => 'application/pdf'],
                );
        }

        return $mail->salutation('— The Herocom Distribution team');
    }
}

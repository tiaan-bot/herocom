<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ContactMessageReceived extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $senderName,
        public readonly ?string $company,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New enquiry from '.$this->senderName,
            // Reply-To is the submitter, so sales can reply straight to them.
            replyTo: [new Address($this->email, $this->senderName)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact-message-received');
    }
}

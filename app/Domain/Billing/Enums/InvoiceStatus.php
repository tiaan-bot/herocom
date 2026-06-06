<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Overdue = 'overdue';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Void = 'void';
    case Unknown = 'unknown';

    /**
     * Map a raw Zoho status to our enum, defaulting to Unknown.
     */
    public static function fromZoho(?string $status): self
    {
        return self::tryFrom((string) $status) ?? self::Unknown;
    }
}

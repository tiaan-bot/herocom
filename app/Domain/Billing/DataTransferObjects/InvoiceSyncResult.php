<?php

declare(strict_types=1);

namespace App\Domain\Billing\DataTransferObjects;

final readonly class InvoiceSyncResult
{
    public function __construct(
        public int $synced,
        public int $skipped, // invoices for Zoho customers with no portal company
    ) {}
}

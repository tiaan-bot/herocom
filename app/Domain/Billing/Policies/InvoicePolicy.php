<?php

declare(strict_types=1);

namespace App\Domain\Billing\Policies;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_invoices');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $user->can('view_invoices')) {
            return false;
        }

        // Drafts live only on Zoho's side — never exposed in the portal.
        if ($invoice->status === InvoiceStatus::Draft) {
            return false;
        }

        // Internal staff (no company) see all invoices; resellers see only their company's.
        return $user->company_id === null || $user->company_id === $invoice->company_id;
    }
}

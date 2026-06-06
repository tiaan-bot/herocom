<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Actions;

use App\Domain\Onboarding\Models\Company;
use App\Domain\Shared\Zoho\Exceptions\ZohoException;
use App\Domain\Shared\Zoho\ZohoClient;

/**
 * Ensures a Company exists as a Zoho Books contact (customer), creating it on
 * first need and persisting the unique zoho_customer_id. Idempotent — used by
 * both the onboarding company push and the order push.
 */
final class EnsureZohoCustomerAction
{
    public function __construct(
        private readonly ZohoClient $zoho,
    ) {}

    public function execute(Company $company): string
    {
        if (filled($company->zoho_customer_id)) {
            return (string) $company->zoho_customer_id;
        }

        $contact = $this->zoho->createContact([
            'contact_name' => $company->legal_name,
            'company_name' => $company->legal_name,
            'contact_type' => 'customer',
        ]);

        $contactId = (string) ($contact['contact_id'] ?? '');
        if ($contactId === '') {
            throw ZohoException::missing('contact_id');
        }

        $company->update(['zoho_customer_id' => $contactId]);

        return $contactId;
    }
}

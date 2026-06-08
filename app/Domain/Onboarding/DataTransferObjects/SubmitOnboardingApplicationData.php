<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\DataTransferObjects;

use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\EntityType;
use App\Domain\Onboarding\Enums\TurnoverBand;

final readonly class SubmitOnboardingApplicationData
{
    /**
     * @param  list<PrincipalData>  $principals
     * @param  list<DocumentUploadData>  $documents
     * @param  array<string, mixed>|null  $cgicPayload
     */
    public function __construct(
        // Company
        public string $legalName,
        public EntityType $entityType,
        public string $addressLine1,
        public string $city,
        public string $province,
        public string $postalCode,
        // Branch + applicant
        public AccountType $accountType,
        public string $contactName,
        public string $contactEmail,
        public string $contactPhone,
        // Consent
        public string $termsVersion,
        public bool $termsAccepted,
        public bool $popiaConsent,
        // Declaration & signature (B1) — drawn signature as a base64 image/png data URL.
        public string $signedByName,
        public string $signedByCapacity,
        public string $signature,
        // Collections
        public array $principals = [],
        public array $documents = [],
        // Company (optional)
        public ?string $tradingName = null,
        public ?string $registrationNumber = null,
        public ?string $vatNumber = null,
        public ?string $natureOfBusiness = null,
        public ?string $addressLine2 = null,
        public string $countryCode = 'ZA',
        public string $currency = 'ZAR',
        // Premises
        public ?bool $premisesOwned = null,
        public ?string $landlordName = null,
        public ?string $landlordAddress = null,
        public ?string $landlordTel = null,
        public ?string $periodAtAddress = null,
        // Credit branch
        public ?float $creditLimitRequested = null,
        public string $creditLimitRequestedCurrency = 'ZAR',
        public ?int $creditTermsRequestedDays = null,
        public ?TurnoverBand $annualTurnoverBand = null,
        public ?array $cgicPayload = null,
        public bool $creditEnquiryConsent = false,
    ) {}

    public function isCredit(): bool
    {
        return $this->accountType === AccountType::Credit;
    }
}

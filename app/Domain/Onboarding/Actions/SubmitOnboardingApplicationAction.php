<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Actions;

use App\Domain\Onboarding\DataTransferObjects\DocumentUploadData;
use App\Domain\Onboarding\DataTransferObjects\PrincipalData;
use App\Domain\Onboarding\DataTransferObjects\SubmitOnboardingApplicationData;
use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Enums\VerificationStatus;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\DatabaseManager;

final class SubmitOnboardingApplicationAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly FilesystemFactory $filesystem,
    ) {}

    public function execute(SubmitOnboardingApplicationData $data): OnboardingApplication
    {
        return $this->db->transaction(function () use ($data): OnboardingApplication {
            $now = CarbonImmutable::now();

            $company = Company::create([
                'legal_name' => $data->legalName,
                'trading_name' => $data->tradingName,
                'entity_type' => $data->entityType,
                'registration_number' => $data->registrationNumber,
                'vat_number' => $data->vatNumber,
                'nature_of_business' => $data->natureOfBusiness,
                'status' => CompanyStatus::Pending,
                'credit_terms' => CreditTerms::EftUpfront,
                'address_line1' => $data->addressLine1,
                'address_line2' => $data->addressLine2,
                'city' => $data->city,
                'province' => $data->province,
                'postal_code' => $data->postalCode,
                'country_code' => $data->countryCode,
                'currency' => $data->currency,
            ]);

            $application = $company->applications()->create([
                'account_type_requested' => $data->accountType,
                'status' => ApplicationStatus::Submitted,
                'contact_name' => $data->contactName,
                'contact_email' => $data->contactEmail,
                'contact_phone' => $data->contactPhone,
                'premises_owned' => $data->premisesOwned,
                'landlord_name' => $data->landlordName,
                'landlord_address' => $data->landlordAddress,
                'landlord_tel' => $data->landlordTel,
                'period_at_address' => $data->periodAtAddress,
                'credit_limit_requested' => $data->isCredit() ? $data->creditLimitRequested : null,
                'credit_limit_requested_currency' => $data->creditLimitRequestedCurrency,
                'credit_terms_requested_days' => $data->isCredit() ? $data->creditTermsRequestedDays : null,
                'annual_turnover_band' => $data->isCredit() ? $data->annualTurnoverBand : null,
                'cgic_payload' => $data->isCredit() && $data->cgicPayload !== null
                    ? json_encode($data->cgicPayload, JSON_THROW_ON_ERROR)
                    : null,
                'cgic_status' => $data->isCredit() ? CgicStatus::Pending : CgicStatus::NotRequired,
                'terms_version' => $data->termsVersion,
                'terms_accepted_at' => $data->termsAccepted ? $now : null,
                'popia_consent_at' => $data->popiaConsent ? $now : null,
                'credit_enquiry_consent_at' => $data->isCredit() && $data->creditEnquiryConsent ? $now : null,
                'submitted_at' => $now,
                'signed_by_name' => $data->signedByName,
                'signed_by_capacity' => $data->signedByCapacity,
                'signed_at' => $now,
            ]);

            foreach ($data->principals as $principal) {
                $this->createPrincipal($application, $principal);
            }

            foreach ($data->documents as $document) {
                $this->storeDocument($application, $document);
            }

            $this->storeSignature($application, $data->signature);

            return $application;
        });
    }

    private function createPrincipal(OnboardingApplication $application, PrincipalData $principal): void
    {
        $application->principals()->create([
            'full_name' => $principal->fullName,
            'surname' => $principal->surname,
            'id_number' => $principal->idNumber,
            'shareholding_percent' => $principal->shareholdingPercent,
            'residential_address_line1' => $principal->residentialAddressLine1,
            'residential_address_line2' => $principal->residentialAddressLine2,
            'residential_city' => $principal->residentialCity,
            'residential_province' => $principal->residentialProvince,
            'residential_postal_code' => $principal->residentialPostalCode,
            'country_code' => $principal->countryCode,
            'is_surety' => $principal->isSurety,
            'married_in_community' => $principal->marriedInCommunity,
        ]);
    }

    private function storeDocument(OnboardingApplication $application, DocumentUploadData $document): void
    {
        $diskName = config('onboarding.documents.disk');
        $disk = $this->filesystem->disk($diskName);

        // Private path namespaced by the application's public uuid.
        $path = $disk->putFile("onboarding/{$application->uuid}", $document->file);

        $application->documents()->create([
            'type' => $document->type,
            'disk' => $diskName,
            'path' => $path,
            'original_filename' => $document->file->getClientOriginalName(),
            'mime_type' => $document->file->getClientMimeType(),
            'size_bytes' => $document->file->getSize(),
            'verification_status' => VerificationStatus::Pending,
            // uploaded_by is null — submitted pre-authentication by the applicant.
        ]);
    }

    /**
     * Decode the drawn signature (base64 image/png data URL) and store the PNG on
     * the private onboarding disk, alongside the application's documents.
     */
    private function storeSignature(OnboardingApplication $application, string $dataUrl): void
    {
        $diskName = config('onboarding.documents.disk');
        $disk = $this->filesystem->disk($diskName);

        $base64 = (string) preg_replace('#^data:image/png;base64,#', '', $dataUrl);
        $binary = base64_decode($base64, true);

        if ($binary === false) {
            return;
        }

        $path = "onboarding/{$application->uuid}/signature.png";
        $disk->put($path, $binary);

        $application->forceFill(['signature_path' => $path])->save();
    }
}

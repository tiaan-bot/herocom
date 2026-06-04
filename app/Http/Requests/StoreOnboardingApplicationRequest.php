<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Onboarding\DataTransferObjects\DocumentUploadData;
use App\Domain\Onboarding\DataTransferObjects\PrincipalData;
use App\Domain\Onboarding\DataTransferObjects\SubmitOnboardingApplicationData;
use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\DocumentType;
use App\Domain\Onboarding\Enums\EntityType;
use App\Domain\Onboarding\Enums\TurnoverBand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;

class StoreOnboardingApplicationRequest extends FormRequest
{
    /**
     * Public onboarding endpoint — gated by throttle + verified middleware on the route,
     * not by an ability.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCredit = $this->input('account_type_requested') === AccountType::Credit->value;
        $entityType = $this->input('entity_type');
        $needsCipc = in_array($entityType, [EntityType::Company->value, EntityType::CloseCorporation->value], true);
        $hasVat = filled($this->input('vat_number'));

        return [
            // Company
            'legal_name' => ['required', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'entity_type' => ['required', new Enum(EntityType::class)],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'nature_of_business' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'size:3'],

            // Branch + applicant
            'account_type_requested' => ['required', new Enum(AccountType::class)],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:40'],

            // Premises
            'premises_owned' => ['nullable', 'boolean'],
            'landlord_name' => ['nullable', 'string', 'max:255'],
            'landlord_address' => ['nullable', 'string', 'max:255'],
            'landlord_tel' => ['nullable', 'string', 'max:40'],
            'period_at_address' => ['nullable', 'string', 'max:255'],

            // Credit branch
            'credit_limit_requested' => [Rule::requiredIf($isCredit), 'nullable', 'numeric', 'min:0'],
            'credit_limit_requested_currency' => ['nullable', 'string', 'size:3'],
            'credit_terms_requested_days' => [Rule::requiredIf($isCredit), 'nullable', 'integer', Rule::in([7, 15, 30])],
            'annual_turnover_band' => [Rule::requiredIf($isCredit), 'nullable', new Enum(TurnoverBand::class)],
            'cgic_payload' => [Rule::requiredIf($isCredit), 'nullable', 'array'],

            // Principals / sureties (credit path)
            'principals' => [Rule::requiredIf($isCredit), 'array', ...($isCredit ? ['min:1'] : [])],
            'principals.*.full_name' => ['required', 'string', 'max:255'],
            'principals.*.surname' => ['required', 'string', 'max:255'],
            'principals.*.id_number' => ['required', 'string', 'max:20'],
            'principals.*.shareholding_percent' => ['nullable', 'numeric', 'between:0,100'],
            'principals.*.is_surety' => ['nullable', 'boolean'],
            'principals.*.married_in_community' => ['nullable', 'boolean'],

            // Documents — required set depends on branch + entity type.
            'documents.signed_application_form' => ['required', ...$this->fileRules()],
            'documents.id_document' => ['required', ...$this->fileRules()],
            'documents.cipc_registration' => [Rule::requiredIf($needsCipc), 'nullable', ...$this->fileRules()],
            'documents.vat_certificate' => [Rule::requiredIf($hasVat), 'nullable', ...$this->fileRules()],
            'documents.bank_confirmation' => [Rule::requiredIf($isCredit), 'nullable', ...$this->fileRules()],
            'documents.proof_of_address' => [Rule::requiredIf($isCredit), 'nullable', ...$this->fileRules()],
            'documents.deed_of_surety' => [Rule::requiredIf($isCredit), 'nullable', ...$this->fileRules()],

            // Consent
            'terms_version' => ['required', 'string', 'max:50'],
            'terms_accepted' => ['accepted'],
            'popia_consent' => ['accepted'],
            'credit_enquiry_consent' => $isCredit ? ['accepted'] : ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function fileRules(): array
    {
        return ['file', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10 * 1024)];
    }

    public function toData(): SubmitOnboardingApplicationData
    {
        $isCredit = $this->input('account_type_requested') === AccountType::Credit->value;

        return new SubmitOnboardingApplicationData(
            legalName: (string) $this->string('legal_name'),
            entityType: EntityType::from((string) $this->string('entity_type')),
            addressLine1: (string) $this->string('address_line1'),
            city: (string) $this->string('city'),
            province: (string) $this->string('province'),
            postalCode: (string) $this->string('postal_code'),
            accountType: AccountType::from((string) $this->string('account_type_requested')),
            contactName: (string) $this->string('contact_name'),
            contactEmail: (string) $this->string('contact_email'),
            contactPhone: (string) $this->string('contact_phone'),
            termsVersion: (string) $this->string('terms_version'),
            termsAccepted: $this->boolean('terms_accepted'),
            popiaConsent: $this->boolean('popia_consent'),
            principals: $this->principalData(),
            documents: $this->documentData(),
            tradingName: $this->input('trading_name'),
            registrationNumber: $this->input('registration_number'),
            vatNumber: $this->input('vat_number'),
            natureOfBusiness: $this->input('nature_of_business'),
            addressLine2: $this->input('address_line2'),
            countryCode: (string) ($this->input('country_code') ?? 'ZA'),
            currency: (string) ($this->input('currency') ?? 'ZAR'),
            premisesOwned: $this->has('premises_owned') ? $this->boolean('premises_owned') : null,
            landlordName: $this->input('landlord_name'),
            landlordAddress: $this->input('landlord_address'),
            landlordTel: $this->input('landlord_tel'),
            periodAtAddress: $this->input('period_at_address'),
            creditLimitRequested: $this->has('credit_limit_requested') ? (float) $this->input('credit_limit_requested') : null,
            creditLimitRequestedCurrency: (string) ($this->input('credit_limit_requested_currency') ?? 'ZAR'),
            creditTermsRequestedDays: $this->has('credit_terms_requested_days') ? (int) $this->input('credit_terms_requested_days') : null,
            annualTurnoverBand: $this->filled('annual_turnover_band') ? TurnoverBand::from((string) $this->string('annual_turnover_band')) : null,
            cgicPayload: $this->input('cgic_payload'),
            creditEnquiryConsent: $isCredit && $this->boolean('credit_enquiry_consent'),
        );
    }

    /**
     * @return list<PrincipalData>
     */
    private function principalData(): array
    {
        /** @var array<int, array<string, mixed>> $principals */
        $principals = $this->input('principals', []);

        return array_map(fn (array $p): PrincipalData => new PrincipalData(
            fullName: (string) ($p['full_name'] ?? ''),
            surname: (string) ($p['surname'] ?? ''),
            idNumber: (string) ($p['id_number'] ?? ''),
            shareholdingPercent: isset($p['shareholding_percent']) ? (float) $p['shareholding_percent'] : null,
            residentialAddressLine1: $p['residential_address_line1'] ?? null,
            residentialAddressLine2: $p['residential_address_line2'] ?? null,
            residentialCity: $p['residential_city'] ?? null,
            residentialProvince: $p['residential_province'] ?? null,
            residentialPostalCode: $p['residential_postal_code'] ?? null,
            countryCode: (string) ($p['country_code'] ?? 'ZA'),
            isSurety: (bool) ($p['is_surety'] ?? true),
            marriedInCommunity: isset($p['married_in_community']) ? (bool) $p['married_in_community'] : null,
        ), array_values($principals));
    }

    /**
     * @return list<DocumentUploadData>
     */
    private function documentData(): array
    {
        $documents = [];

        foreach (DocumentType::cases() as $type) {
            $file = $this->file("documents.{$type->value}");

            if ($file instanceof UploadedFile) {
                $documents[] = new DocumentUploadData($type, $file);
            }
        }

        return $documents;
    }
}

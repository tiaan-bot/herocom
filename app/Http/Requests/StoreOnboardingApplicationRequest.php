<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Onboarding\DataTransferObjects\DocumentUploadData;
use App\Domain\Onboarding\DataTransferObjects\PrincipalData;
use App\Domain\Onboarding\DataTransferObjects\SubmitOnboardingApplicationData;
use App\Domain\Onboarding\DataTransferObjects\TradeReferenceData;
use App\Domain\Onboarding\Enums\AccountHeld;
use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\BankAccountType;
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
     * Normalise blank optional inputs to null so 'nullable' short-circuits the
     * enum/numeric rules (the multipart form posts "" for untouched fields).
     */
    protected function prepareForValidation(): void
    {
        $nullableIfBlank = [
            'trading_name', 'registration_number', 'vat_number', 'nature_of_business',
            'address_line2', 'landlord_name', 'landlord_address', 'landlord_tel',
            'period_at_address', 'credit_limit_requested', 'credit_terms_requested_days',
            'annual_turnover_band',
            // Credit-branch extras (blank on the COD branch).
            'date_of_registration', 'company_telephone', 'company_fax',
            'postal_address_line1', 'postal_province', 'postal_postal_code',
            'account_contact_name', 'account_contact_email', 'account_contact_phone',
        ];

        $merge = [];

        foreach ($nullableIfBlank as $field) {
            if ($this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
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
            // Honeypot — must stay empty; bots that fill it are rejected.
            'website' => ['prohibited'],

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

            // Credit branch — extra company details. Date of registration is required
            // for registered companies and close corporations, optional otherwise.
            'date_of_registration' => [Rule::requiredIf($isCredit && $needsCipc), 'nullable', 'date'],
            'company_telephone' => [Rule::requiredIf($isCredit), 'nullable', 'string', 'max:40'],
            'company_fax' => ['nullable', 'string', 'max:40'],
            'postal_address_line1' => [Rule::requiredIf($isCredit), 'nullable', 'string', 'max:255'],
            'postal_province' => [Rule::requiredIf($isCredit), 'nullable', 'string', 'max:255'],
            'postal_postal_code' => [Rule::requiredIf($isCredit), 'nullable', 'string', 'max:20'],

            // Credit branch — banking (stored inside the encrypted cgic_payload)
            'cgic_payload.banking.bank' => [Rule::requiredIf($isCredit), 'nullable', 'string', 'max:255'],
            'cgic_payload.banking.date_opened' => ['nullable', 'date'],
            'cgic_payload.banking.branch_name' => [Rule::requiredIf($isCredit), 'nullable', 'string', 'max:255'],
            'cgic_payload.banking.branch_code' => [Rule::requiredIf($isCredit), 'nullable', 'string', 'max:50'],
            'cgic_payload.banking.account_type' => [Rule::requiredIf($isCredit), 'nullable', new Enum(BankAccountType::class)],
            'cgic_payload.banking.account_number' => [Rule::requiredIf($isCredit), 'nullable', 'string', 'max:50'],
            'cgic_payload.banking.account_name' => [Rule::requiredIf($isCredit), 'nullable', 'string', 'max:255'],

            // Credit branch — account contact person (distinct from the applicant)
            'account_contact_name' => [Rule::requiredIf($isCredit), 'nullable', 'string', 'max:255'],
            'account_contact_email' => [Rule::requiredIf($isCredit), 'nullable', 'email', 'max:255'],
            'account_contact_phone' => [Rule::requiredIf($isCredit), 'nullable', 'string', 'max:40'],

            // Credit branch — trade references (1–3)
            'trade_references' => [Rule::requiredIf($isCredit), 'array', ...($isCredit ? ['min:1', 'max:3'] : ['max:3'])],
            'trade_references.*.company_name' => ['required', 'string', 'max:255'],
            'trade_references.*.credit_limit' => ['nullable', 'numeric', 'min:0'],
            'trade_references.*.account_held' => ['required', new Enum(AccountHeld::class)],
            'trade_references.*.terms_days' => ['nullable', 'integer', Rule::in([7, 15, 30])],

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

            // Declaration & signature (drawn-only; base64 image/png data URL).
            'signed_by_name' => ['required', 'string', 'max:255'],
            'signed_by_capacity' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'string', 'max:5000000', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=\r\n]+$/'],
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
            signedByName: (string) $this->string('signed_by_name'),
            signedByCapacity: (string) $this->string('signed_by_capacity'),
            signature: (string) $this->string('signature'),
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
            dateOfRegistration: $this->input('date_of_registration'),
            companyTelephone: $this->input('company_telephone'),
            companyFax: $this->input('company_fax'),
            postalAddressLine1: $this->input('postal_address_line1'),
            postalProvince: $this->input('postal_province'),
            postalPostalCode: $this->input('postal_postal_code'),
            accountContactName: $this->input('account_contact_name'),
            accountContactEmail: $this->input('account_contact_email'),
            accountContactPhone: $this->input('account_contact_phone'),
            tradeReferences: $this->tradeReferenceData(),
        );
    }

    /**
     * @return list<TradeReferenceData>
     */
    private function tradeReferenceData(): array
    {
        /** @var array<int, array<string, mixed>> $references */
        $references = $this->input('trade_references', []);

        return array_map(fn (array $r): TradeReferenceData => new TradeReferenceData(
            companyName: (string) ($r['company_name'] ?? ''),
            creditLimit: isset($r['credit_limit']) && $r['credit_limit'] !== '' ? (float) $r['credit_limit'] : null,
            accountHeld: AccountHeld::from((string) ($r['account_held'] ?? AccountHeld::Cod->value)),
            termsDays: isset($r['terms_days']) && $r['terms_days'] !== '' ? (int) $r['terms_days'] : null,
        ), array_values($references));
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

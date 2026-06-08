<?php

declare(strict_types=1);

use App\Http\Requests\StoreOnboardingApplicationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function rulesFor(array $payload): array
{
    return StoreOnboardingApplicationRequest::create('/onboarding', 'POST', $payload)->rules();
}

function passesValidation(array $payload): bool
{
    return Validator::make($payload, rulesFor($payload))->passes();
}

function codPayload(array $overrides = []): array
{
    return array_replace([
        'legal_name' => 'Acme',
        'entity_type' => 'sole_proprietor',
        'address_line1' => '1 Main',
        'city' => 'Johannesburg',
        'province' => 'Gauteng',
        'postal_code' => '2000',
        'account_type_requested' => 'cod',
        'contact_name' => 'Jane Doe',
        'contact_email' => 'jane@acme.test',
        'contact_phone' => '0110000000',
        'terms_version' => '2026-01',
        'terms_accepted' => true,
        'popia_consent' => true,
        'signed_by_name' => 'Jane Doe',
        'signed_by_capacity' => 'Director',
        'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        'documents' => [
            'signed_application_form' => UploadedFile::fake()->create('form.pdf', 50, 'application/pdf'),
            'id_document' => UploadedFile::fake()->create('id.pdf', 50, 'application/pdf'),
        ],
    ], $overrides);
}

it('passes a minimal valid COD application', function () {
    expect(passesValidation(codPayload()))->toBeTrue();
});

it('fails COD without the signed application form', function () {
    $payload = codPayload();
    unset($payload['documents']['signed_application_form']);

    expect(passesValidation($payload))->toBeFalse();
});

it('requires a CIPC registration for a company entity', function () {
    $payload = codPayload(['entity_type' => 'company']);

    expect(passesValidation($payload))->toBeFalse();

    $payload['documents']['cipc_registration'] = UploadedFile::fake()->create('cipc.pdf', 50, 'application/pdf');
    expect(passesValidation($payload))->toBeTrue();
});

it('requires a VAT certificate when a vat number is supplied', function () {
    $payload = codPayload(['vat_number' => '4123456789']);

    expect(passesValidation($payload))->toBeFalse();

    $payload['documents']['vat_certificate'] = UploadedFile::fake()->create('vat.pdf', 50, 'application/pdf');
    expect(passesValidation($payload))->toBeTrue();
});

it('requires the deed of surety and credit fields on the credit branch', function () {
    $payload = codPayload([
        'account_type_requested' => 'credit',
        'credit_limit_requested' => 50000,
        'credit_terms_requested_days' => 30,
        'annual_turnover_band' => 'under_2m',
        'cgic_payload' => ['banking' => ['bank' => 'Demo']],
        'credit_enquiry_consent' => true,
        'principals' => [['full_name' => 'Jane', 'surname' => 'Doe', 'id_number' => '9001015800086']],
    ]);
    $payload['documents']['bank_confirmation'] = UploadedFile::fake()->create('bank.pdf', 50, 'application/pdf');
    $payload['documents']['proof_of_address'] = UploadedFile::fake()->create('poa.pdf', 50, 'application/pdf');

    // deed_of_surety still missing → fails
    expect(passesValidation($payload))->toBeFalse();

    $payload['documents']['deed_of_surety'] = UploadedFile::fake()->create('deed.pdf', 50, 'application/pdf');
    expect(passesValidation($payload))->toBeTrue();
});

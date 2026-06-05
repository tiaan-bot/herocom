<?php

declare(strict_types=1);

use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Notifications\ApplicationReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('r2');
    Notification::fake();
});

function fakePdf(): UploadedFile
{
    return UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');
}

function codApplyPayload(array $overrides = []): array
{
    return array_replace([
        'website' => '',
        'account_type_requested' => 'cod',
        'legal_name' => 'Acme Trading',
        'entity_type' => 'sole_proprietor',
        'address_line1' => '1 Main Rd',
        'city' => 'Johannesburg',
        'province' => 'Gauteng',
        'postal_code' => '2000',
        'country_code' => 'ZA',
        'currency' => 'ZAR',
        'contact_name' => 'Jane Doe',
        'contact_email' => 'jane@acme.test',
        'contact_phone' => '0110000000',
        'premises_owned' => true,
        'terms_version' => '2026-01',
        'terms_accepted' => true,
        'popia_consent' => true,
        'documents' => [
            'signed_application_form' => fakePdf(),
            'id_document' => fakePdf(),
        ],
    ], $overrides);
}

function creditApplyPayload(array $overrides = []): array
{
    return array_replace(codApplyPayload(), array_replace([
        'account_type_requested' => 'credit',
        'credit_limit_requested' => 50000,
        'credit_terms_requested_days' => 30,
        'annual_turnover_band' => 'under_2m',
        'credit_enquiry_consent' => true,
        'cgic_payload' => ['banking' => ['bank' => 'Demo Bank']],
        'principals' => [
            ['full_name' => 'Jane', 'surname' => 'Doe', 'id_number' => '9001015800086', 'is_surety' => true],
        ],
        'documents' => [
            'signed_application_form' => fakePdf(),
            'id_document' => fakePdf(),
            'bank_confirmation' => fakePdf(),
            'proof_of_address' => fakePdf(),
            'deed_of_surety' => fakePdf(),
        ],
    ], $overrides));
}

it('renders the apply form', function () {
    $this->get('/apply')->assertOk();
});

it('creates a pending company and submitted COD application', function () {
    $this->post('/apply', codApplyPayload())->assertRedirect(route('apply.success'));

    $company = Company::sole();
    $application = OnboardingApplication::sole();

    expect($company->status)->toBe(CompanyStatus::Pending)
        ->and($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->account_type_requested)->toBe(AccountType::Cod)
        ->and($application->cgic_status)->toBe(CgicStatus::NotRequired)
        ->and($application->documents)->toHaveCount(2);

    Notification::assertSentOnDemand(ApplicationReceivedNotification::class);
});

it('records consent timestamps on submit', function () {
    $this->post('/apply', codApplyPayload());

    $application = OnboardingApplication::sole();
    expect($application->terms_accepted_at)->not->toBeNull()
        ->and($application->terms_version)->toBe('2026-01')
        ->and($application->popia_consent_at)->not->toBeNull();
});

it('creates a credit application with principals and credit fields', function () {
    $this->post('/apply', creditApplyPayload())->assertRedirect(route('apply.success'));

    $application = OnboardingApplication::sole();
    expect($application->account_type_requested)->toBe(AccountType::Credit)
        ->and($application->cgic_status)->toBe(CgicStatus::Pending)
        ->and($application->credit_terms_requested_days)->toBe(30)
        ->and($application->principals)->toHaveCount(1)
        ->and($application->credit_enquiry_consent_at)->not->toBeNull();
});

it('rejects a credit application missing the deed of surety', function () {
    $payload = creditApplyPayload();
    unset($payload['documents']['deed_of_surety']);

    $this->post('/apply', $payload)->assertSessionHasErrors('documents.deed_of_surety');
    expect(Company::count())->toBe(0);
});

it('rejects a company entity missing its CIPC registration', function () {
    $payload = codApplyPayload(['entity_type' => 'company']);

    $this->post('/apply', $payload)->assertSessionHasErrors('documents.cipc_registration');
    expect(OnboardingApplication::count())->toBe(0);
});

it('rejects a submission when the honeypot is filled', function () {
    $this->post('/apply', codApplyPayload(['website' => 'http://spam.test']))
        ->assertSessionHasErrors('website');

    expect(Company::count())->toBe(0);
});

it('throttles repeated submissions from the same client', function () {
    $status = null;
    for ($i = 0; $i < 6; $i++) {
        $status = $this->post('/apply', [])->getStatusCode();
    }

    expect($status)->toBe(429);
});

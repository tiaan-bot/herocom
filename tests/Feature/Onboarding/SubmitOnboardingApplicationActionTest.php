<?php

declare(strict_types=1);

use App\Domain\Onboarding\Actions\SubmitOnboardingApplicationAction;
use App\Domain\Onboarding\DataTransferObjects\DocumentUploadData;
use App\Domain\Onboarding\DataTransferObjects\PrincipalData;
use App\Domain\Onboarding\DataTransferObjects\SubmitOnboardingApplicationData;
use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Enums\DocumentType;
use App\Domain\Onboarding\Enums\EntityType;
use App\Domain\Onboarding\Enums\TurnoverBand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('r2');
});

function pdf(string $name): UploadedFile
{
    return UploadedFile::fake()->create($name, 50, 'application/pdf');
}

function codData(array $overrides = []): SubmitOnboardingApplicationData
{
    $defaults = [
        'legalName' => 'Acme Trading',
        'entityType' => EntityType::Company,
        'addressLine1' => '1 Main Rd',
        'city' => 'Johannesburg',
        'province' => 'Gauteng',
        'postalCode' => '2000',
        'accountType' => AccountType::Cod,
        'contactName' => 'Jane Doe',
        'contactEmail' => 'jane@acme.test',
        'contactPhone' => '0110000000',
        'termsVersion' => '2026-01',
        'termsAccepted' => true,
        'popiaConsent' => true,
        'documents' => [new DocumentUploadData(DocumentType::SignedApplicationForm, pdf('form.pdf'))],
    ];

    return new SubmitOnboardingApplicationData(...array_replace($defaults, $overrides));
}

it('creates a pending COD company and submitted application', function () {
    $application = app(SubmitOnboardingApplicationAction::class)->execute(codData());

    expect($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->cgic_status)->toBe(CgicStatus::NotRequired)
        ->and($application->company->status)->toBe(CompanyStatus::Pending)
        ->and($application->company->credit_terms)->toBe(CreditTerms::EftUpfront)
        ->and($application->terms_accepted_at)->not->toBeNull()
        ->and($application->popia_consent_at)->not->toBeNull()
        ->and($application->terms_version)->toBe('2026-01');
});

it('stores submitted documents on the private r2 disk', function () {
    $application = app(SubmitOnboardingApplicationAction::class)->execute(codData());

    $document = $application->documents->first();

    expect($application->documents)->toHaveCount(1)
        ->and($document->disk)->toBe('r2');
    Storage::disk('r2')->assertExists($document->path);
});

it('builds a credit application with principals and an encrypted cgic payload', function () {
    $payload = ['banking' => ['bank' => 'Demo Bank'], 'trade_references' => [['name' => 'Ref']]];

    $data = codData([
        'accountType' => AccountType::Credit,
        'creditLimitRequested' => 50000.0,
        'creditTermsRequestedDays' => 30,
        'annualTurnoverBand' => TurnoverBand::Under2m,
        'cgicPayload' => $payload,
        'creditEnquiryConsent' => true,
        'principals' => [new PrincipalData(fullName: 'Jane', surname: 'Doe', idNumber: '9001015800086', shareholdingPercent: 100.0)],
        'documents' => [
            new DocumentUploadData(DocumentType::SignedApplicationForm, pdf('form.pdf')),
            new DocumentUploadData(DocumentType::DeedOfSurety, pdf('deed.pdf')),
        ],
    ]);

    $application = app(SubmitOnboardingApplicationAction::class)->execute($data);

    expect($application->cgic_status)->toBe(CgicStatus::Pending)
        ->and($application->principals)->toHaveCount(1)
        ->and($application->credit_enquiry_consent_at)->not->toBeNull()
        ->and($application->credit_terms_requested_days)->toBe(30);

    $rawPayload = DB::table('onboarding_applications')->where('id', $application->id)->value('cgic_payload');
    expect(Crypt::decryptString($rawPayload))->toBe(json_encode($payload));
});

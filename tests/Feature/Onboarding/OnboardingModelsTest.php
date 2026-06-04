<?php

declare(strict_types=1);

use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Enums\DocumentType;
use App\Domain\Onboarding\Enums\VerificationStatus;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Onboarding\Models\OnboardingDocument;
use App\Domain\Onboarding\Models\OnboardingPrincipal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('auto-generates a uuid and uses it as the route key', function () {
    $company = Company::factory()->create();

    expect($company->uuid)->not->toBeNull()
        ->and($company->getRouteKeyName())->toBe('uuid');
});

it('casts company columns to enums and decimals', function () {
    $company = Company::factory()->create();

    expect($company->status)->toBeInstanceOf(CompanyStatus::class)
        ->and($company->status)->toBe(CompanyStatus::Pending)
        ->and($company->credit_terms)->toBe(CreditTerms::EftUpfront)
        ->and($company->discount_percent)->toBe('0.00');
});

it('scopes companies by status', function () {
    Company::factory()->count(2)->create();
    Company::factory()->approved()->create();

    expect(Company::approved()->count())->toBe(1)
        ->and(Company::pending()->count())->toBe(2);
});

it('defaults an application to the COD branch with cgic not required', function () {
    $application = OnboardingApplication::factory()->create();

    expect($application->account_type_requested)->toBe(AccountType::Cod)
        ->and($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->cgic_status)->toBe(CgicStatus::NotRequired);
});

it('builds the credit branch state', function () {
    $application = OnboardingApplication::factory()->credit()->create();

    expect($application->account_type_requested)->toBe(AccountType::Credit)
        ->and($application->cgic_status)->toBe(CgicStatus::Pending)
        ->and($application->credit_terms_requested_days)->toBe(30);
});

it('wires application relations', function () {
    $application = OnboardingApplication::factory()
        ->has(OnboardingPrincipal::factory()->count(2), 'principals')
        ->has(OnboardingDocument::factory()->count(3), 'documents')
        ->create();

    expect($application->company)->toBeInstanceOf(Company::class)
        ->and($application->principals)->toHaveCount(2)
        ->and($application->documents)->toHaveCount(3);
});

it('casts document type and verification status', function () {
    $document = OnboardingDocument::factory()->ofType(DocumentType::BankConfirmation)->verified()->create();

    expect($document->type)->toBe(DocumentType::BankConfirmation)
        ->and($document->verification_status)->toBe(VerificationStatus::Verified)
        ->and($document->uuid)->not->toBeNull();
});

it('soft deletes a company but keeps the row', function () {
    $company = Company::factory()->create();
    $company->delete();

    expect(Company::count())->toBe(0)
        ->and(Company::withTrashed()->count())->toBe(1);
});

it('relates reseller users to a company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    expect($user->company->is($company))->toBeTrue()
        ->and($company->users)->toHaveCount(1);
});

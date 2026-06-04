<?php

declare(strict_types=1);

use App\Domain\Onboarding\Actions\ApproveOnboardingApplicationAction;
use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Events\CompanyApproved;
use App\Domain\Onboarding\Exceptions\OnboardingDecisionException;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function approve(): ApproveOnboardingApplicationAction
{
    return app(ApproveOnboardingApplicationAction::class);
}

it('approves a COD application, provisions an owner, and dispatches CompanyApproved', function () {
    Event::fake([CompanyApproved::class]);
    $application = OnboardingApplication::factory()->create();

    $company = approve()->execute($application, userWithRole('sales_admin'), 'all good');

    expect($company->status)->toBe(CompanyStatus::Approved)
        ->and($company->credit_terms)->toBe(CreditTerms::EftUpfront)
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Approved);

    $owner = User::where('email', $application->contact_email)->first();
    expect($owner)->not->toBeNull()
        ->and($owner->company_id)->toBe($company->id)
        ->and($owner->hasRole('reseller_owner'))->toBeTrue();

    Event::assertDispatched(CompanyApproved::class);
});

it('refuses to approve a credit application until CGIC approves', function () {
    $application = OnboardingApplication::factory()->credit()->create();

    expect(fn () => approve()->execute($application, userWithRole('sales_admin')))
        ->toThrow(OnboardingDecisionException::class);
});

it('approves a credit application once CGIC has approved and sets on_account', function () {
    Event::fake([CompanyApproved::class]);
    $application = OnboardingApplication::factory()->credit()->create(['cgic_status' => CgicStatus::Approved]);

    $company = approve()->execute($application, userWithRole('sales_admin'));

    expect($company->credit_terms)->toBe(CreditTerms::OnAccount);
});

it('refuses to approve an already-decided application', function () {
    $application = OnboardingApplication::factory()->approved()->create();

    expect(fn () => approve()->execute($application, userWithRole('sales_admin')))
        ->toThrow(OnboardingDecisionException::class);
});

it('denies approval to a user without the ability', function () {
    $application = OnboardingApplication::factory()->create();

    expect(fn () => approve()->execute($application, User::factory()->create()))
        ->toThrow(AuthorizationException::class);
});

it('allows super_admin to approve via the gate bypass', function () {
    Event::fake([CompanyApproved::class]);
    $application = OnboardingApplication::factory()->create();

    $company = approve()->execute($application, userWithRole('super_admin'));

    expect($company->status)->toBe(CompanyStatus::Approved);
});

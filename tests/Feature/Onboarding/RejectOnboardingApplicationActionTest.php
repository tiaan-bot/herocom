<?php

declare(strict_types=1);

use App\Domain\Onboarding\Actions\RejectOnboardingApplicationAction;
use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Exceptions\OnboardingDecisionException;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('rejects an application and records the reason on both records', function () {
    $application = OnboardingApplication::factory()->create();

    $company = app(RejectOnboardingApplicationAction::class)
        ->execute($application, userWithRole('sales_admin'), 'Incomplete documents');

    expect($company->status)->toBe(CompanyStatus::Rejected)
        ->and($company->rejection_reason)->toBe('Incomplete documents')
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Rejected)
        ->and($application->fresh()->decision_notes)->toBe('Incomplete documents');
});

it('refuses to reject an already-decided application', function () {
    $application = OnboardingApplication::factory()->approved()->create();

    expect(fn () => app(RejectOnboardingApplicationAction::class)
        ->execute($application, userWithRole('sales_admin'), 'too late'))
        ->toThrow(OnboardingDecisionException::class);
});

it('denies rejection to a user without the ability', function () {
    $application = OnboardingApplication::factory()->create();

    expect(fn () => app(RejectOnboardingApplicationAction::class)
        ->execute($application, User::factory()->create(), 'nope'))
        ->toThrow(AuthorizationException::class);
});

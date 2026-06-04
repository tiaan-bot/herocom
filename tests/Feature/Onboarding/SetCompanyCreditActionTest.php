<?php

declare(strict_types=1);

use App\Domain\Onboarding\Actions\SetCompanyCreditAction;
use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('sets on_account terms with a limit and terms days', function () {
    $company = Company::factory()->approved()->create();

    app(SetCompanyCreditAction::class)
        ->execute($company, userWithRole('finance_admin'), CreditTerms::OnAccount, 50000.0, 30);

    $company->refresh();
    expect($company->credit_terms)->toBe(CreditTerms::OnAccount)
        ->and((float) $company->credit_limit)->toBe(50000.0)
        ->and($company->credit_terms_days)->toBe(30);
});

it('downgrades to eft_upfront and clears limit and terms days', function () {
    $company = Company::factory()->approved()->onAccount()->create();

    app(SetCompanyCreditAction::class)
        ->execute($company, userWithRole('finance_admin'), CreditTerms::EftUpfront);

    $company->refresh();
    expect($company->credit_terms)->toBe(CreditTerms::EftUpfront)
        ->and($company->credit_limit)->toBeNull()
        ->and($company->credit_terms_days)->toBeNull();
});

it('denies credit changes to a user without the ability', function () {
    $company = Company::factory()->create();

    expect(fn () => app(SetCompanyCreditAction::class)
        ->execute($company, User::factory()->create(), CreditTerms::OnAccount, 1000.0, 7))
        ->toThrow(AuthorizationException::class);
});

it('allows finance_admin but not sales_admin to manage credit', function () {
    $company = Company::factory()->create();

    expect(fn () => app(SetCompanyCreditAction::class)
        ->execute($company, userWithRole('sales_admin'), CreditTerms::OnAccount, 1000.0, 7))
        ->toThrow(AuthorizationException::class);
});

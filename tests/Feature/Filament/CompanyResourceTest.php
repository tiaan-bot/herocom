<?php

declare(strict_types=1);

use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Models\Company;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function viewCompany(Company $company): Testable
{
    return Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()]);
}

it('shows the set-credit action to finance but not to a viewer', function () {
    $company = Company::factory()->approved()->create();

    $this->actingAs(userWithRole('finance_admin'));
    viewCompany($company)->assertActionVisible('setCredit');

    $this->actingAs(userWithRole('viewer'));
    viewCompany($company)->assertActionHidden('setCredit');
});

it('sets on_account terms through SetCompanyCreditAction', function () {
    $company = Company::factory()->approved()->create();
    $this->actingAs(userWithRole('finance_admin'));

    viewCompany($company)->callAction('setCredit', data: [
        'credit_terms' => CreditTerms::OnAccount->value,
        'credit_limit' => 75000,
        'credit_limit_currency' => 'ZAR',
        'credit_terms_days' => 30,
    ]);

    $company->refresh();
    expect($company->credit_terms)->toBe(CreditTerms::OnAccount)
        ->and((float) $company->credit_limit)->toBe(75000.0)
        ->and($company->credit_terms_days)->toBe(30);
});

it('downgrades to eft_upfront and clears the limit and terms days', function () {
    $company = Company::factory()->approved()->onAccount()->create();
    $this->actingAs(userWithRole('finance_admin'));

    viewCompany($company)->callAction('setCredit', data: [
        'credit_terms' => CreditTerms::EftUpfront->value,
    ]);

    $company->refresh();
    expect($company->credit_terms)->toBe(CreditTerms::EftUpfront)
        ->and($company->credit_limit)->toBeNull()
        ->and($company->credit_terms_days)->toBeNull();
});

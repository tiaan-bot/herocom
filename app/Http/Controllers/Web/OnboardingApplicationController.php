<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Onboarding\Actions\SubmitOnboardingApplicationAction;
use App\Domain\Onboarding\Enums\AccountHeld;
use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\BankAccountType;
use App\Domain\Onboarding\Enums\DocumentType;
use App\Domain\Onboarding\Enums\EntityType;
use App\Domain\Onboarding\Enums\TurnoverBand;
use App\Domain\Onboarding\Jobs\GenerateApplicationPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOnboardingApplicationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingApplicationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Onboarding/Apply', [
            'termsVersion' => config('onboarding.terms.version'),
            'termsUrl' => config('onboarding.terms.url'),
            'accountTypes' => $this->options(AccountType::cases()),
            'entityTypes' => $this->options(EntityType::cases()),
            'turnoverBands' => $this->options(TurnoverBand::cases()),
            'creditTermsDays' => [7, 15, 30],
            'bankAccountTypes' => $this->options(BankAccountType::cases()),
            'accountHeldOptions' => $this->options(AccountHeld::cases()),
            'documentTypes' => $this->documentTypes(),
        ]);
    }

    public function store(StoreOnboardingApplicationRequest $request, SubmitOnboardingApplicationAction $action): RedirectResponse
    {
        $application = $action->execute($request->toData());

        // The queued job builds the PDF, records it, and emails the applicant a
        // copy attached to their confirmation (see GenerateApplicationPdf).
        GenerateApplicationPdf::dispatch($application);

        return redirect()->route('apply.success');
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn (\BackedEnum $case): array => [
            'value' => (string) $case->value,
            'label' => (string) str((string) $case->value)->headline(),
        ], $cases);
    }

    /**
     * Presentational requirement matrix mirrored from StoreOnboardingApplicationRequest.
     * `rule`: always | credit | entity_company_cc | has_vat.
     *
     * @return array<int, array{value: string, label: string, rule: string}>
     */
    private function documentTypes(): array
    {
        return [
            ['value' => DocumentType::SignedApplicationForm->value, 'label' => 'Signed application form', 'rule' => 'always'],
            ['value' => DocumentType::IdDocument->value, 'label' => 'ID document(s) of signatories', 'rule' => 'always'],
            ['value' => DocumentType::CipcRegistration->value, 'label' => 'CIPC registration', 'rule' => 'entity_company_cc'],
            ['value' => DocumentType::VatCertificate->value, 'label' => 'VAT certificate', 'rule' => 'has_vat'],
            ['value' => DocumentType::BankConfirmation->value, 'label' => 'Bank confirmation letter', 'rule' => 'credit'],
            ['value' => DocumentType::ProofOfAddress->value, 'label' => 'Proof of address', 'rule' => 'credit'],
            ['value' => DocumentType::DeedOfSurety->value, 'label' => 'Deed of Suretyship (signed)', 'rule' => 'credit'],
        ];
    }
}

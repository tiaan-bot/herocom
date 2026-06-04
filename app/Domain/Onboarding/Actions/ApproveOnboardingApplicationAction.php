<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Actions;

use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Events\CompanyApproved;
use App\Domain\Onboarding\Exceptions\OnboardingDecisionException;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

final class ApproveOnboardingApplicationAction
{
    /**
     * States from which an application may still be decided.
     */
    private const DECIDABLE = [
        ApplicationStatus::Submitted,
        ApplicationStatus::UnderReview,
        ApplicationStatus::InfoRequested,
    ];

    public function __construct(
        private readonly Gate $gate,
        private readonly Dispatcher $events,
        private readonly DatabaseManager $db,
    ) {}

    public function execute(OnboardingApplication $application, User $reviewer, ?string $decisionNotes = null): Company
    {
        $this->gate->forUser($reviewer)->authorize('approve', $application);

        if (! in_array($application->status, self::DECIDABLE, true)) {
            throw OnboardingDecisionException::notDecidable($application);
        }

        // A credit facility may not be approved until CGIC has approved it.
        if ($application->account_type_requested === AccountType::Credit
            && $application->cgic_status !== CgicStatus::Approved) {
            throw OnboardingDecisionException::creditRequiresCgicApproval($application);
        }

        return $this->db->transaction(function () use ($application, $reviewer, $decisionNotes): Company {
            $now = CarbonImmutable::now();

            $application->update([
                'status' => ApplicationStatus::Approved,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => $now,
                'decision_notes' => $decisionNotes,
            ]);

            $company = $application->company;
            $company->update([
                'status' => CompanyStatus::Approved,
                'approved_by' => $reviewer->getKey(),
                'approved_at' => $now,
                // COD → eft_upfront; credit → on_account. Limit/terms are set later by finance.
                'credit_terms' => $application->account_type_requested === AccountType::Credit
                    ? CreditTerms::OnAccount
                    : CreditTerms::EftUpfront,
            ]);

            $owner = $this->provisionOwner($company, $application);

            $this->events->dispatch(new CompanyApproved($company, $application, $owner));

            return $company;
        });
    }

    /**
     * Create (or attach) the contact as the company's first reseller_owner.
     * No usable password is set — the welcome flow drives password creation.
     */
    private function provisionOwner(Company $company, OnboardingApplication $application): User
    {
        $owner = User::firstOrNew(['email' => $application->contact_email]);

        if (! $owner->exists) {
            $owner->name = $application->contact_name;
            // 'hashed' cast hashes this; the random value is unusable until the user sets one.
            $owner->password = Str::random(40);
        }

        $owner->company_id = $company->getKey();
        $owner->save();

        if (! $owner->hasRole('reseller_owner')) {
            $owner->assignRole('reseller_owner');
        }

        return $owner;
    }
}

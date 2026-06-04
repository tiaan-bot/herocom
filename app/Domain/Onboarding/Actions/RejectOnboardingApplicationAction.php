<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Actions;

use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Exceptions\OnboardingDecisionException;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\DatabaseManager;

final class RejectOnboardingApplicationAction
{
    private const DECIDABLE = [
        ApplicationStatus::Submitted,
        ApplicationStatus::UnderReview,
        ApplicationStatus::InfoRequested,
    ];

    public function __construct(
        private readonly Gate $gate,
        private readonly DatabaseManager $db,
    ) {}

    public function execute(OnboardingApplication $application, User $reviewer, string $reason): Company
    {
        $this->gate->forUser($reviewer)->authorize('reject', $application);

        if (! in_array($application->status, self::DECIDABLE, true)) {
            throw OnboardingDecisionException::notDecidable($application);
        }

        return $this->db->transaction(function () use ($application, $reviewer, $reason): Company {
            $now = CarbonImmutable::now();

            $application->update([
                'status' => ApplicationStatus::Rejected,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => $now,
                'decision_notes' => $reason,
            ]);

            $company = $application->company;
            $company->update([
                'status' => CompanyStatus::Rejected,
                'rejected_by' => $reviewer->getKey(),
                'rejected_at' => $now,
                'rejection_reason' => $reason,
            ]);

            return $company;
        });
    }
}

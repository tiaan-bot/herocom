<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Policies;

use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Models\User;

class OnboardingApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_onboarding_applications');
    }

    public function view(User $user, OnboardingApplication $application): bool
    {
        return $user->can('view_onboarding_applications');
    }

    /**
     * Reviewing/verifying submitted documents.
     */
    public function process(User $user, OnboardingApplication $application): bool
    {
        return $user->can('process_onboarding_applications');
    }

    public function approve(User $user, OnboardingApplication $application): bool
    {
        return $user->can('approve_onboarding_applications');
    }

    /**
     * Recording the CGIC outcome and viewing the CGIC submission packet are
     * credit-risk operations gated to finance.
     */
    public function recordCgic(User $user, OnboardingApplication $application): bool
    {
        return $user->can('manage_company_credit');
    }

    public function reject(User $user, OnboardingApplication $application): bool
    {
        return $user->can('approve_onboarding_applications');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Events;

use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CompanyApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly OnboardingApplication $application,
        public readonly User $owner,
    ) {}
}

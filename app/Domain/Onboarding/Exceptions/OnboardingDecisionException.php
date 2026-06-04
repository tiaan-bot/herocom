<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Exceptions;

use App\Domain\Onboarding\Models\OnboardingApplication;
use DomainException;

final class OnboardingDecisionException extends DomainException
{
    public static function notDecidable(OnboardingApplication $application): self
    {
        return new self(sprintf(
            'Application %s is not in a decidable state (current: %s).',
            $application->uuid,
            $application->status->value,
        ));
    }

    public static function creditRequiresCgicApproval(OnboardingApplication $application): self
    {
        return new self(sprintf(
            'Credit application %s cannot be approved until CGIC has approved (current CGIC status: %s).',
            $application->uuid,
            $application->cgic_status->value,
        ));
    }
}

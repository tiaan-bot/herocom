<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Actions;

use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Gate;

final class RequestApplicationInformationAction
{
    public function __construct(
        private readonly Gate $gate,
    ) {}

    public function execute(OnboardingApplication $application, User $actor, ?string $notes = null): OnboardingApplication
    {
        $this->gate->forUser($actor)->authorize('process', $application);

        $application->update([
            'status' => ApplicationStatus::InfoRequested,
            'decision_notes' => $notes,
        ]);

        return $application;
    }
}

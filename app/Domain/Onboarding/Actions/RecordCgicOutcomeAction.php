<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Actions;

use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Access\Gate;

final class RecordCgicOutcomeAction
{
    public function __construct(
        private readonly Gate $gate,
    ) {}

    public function execute(
        OnboardingApplication $application,
        User $actor,
        CgicStatus $status,
        ?string $reference = null,
        ?string $notes = null,
    ): OnboardingApplication {
        $this->gate->forUser($actor)->authorize('recordCgic', $application);

        $application->update([
            'cgic_status' => $status,
            'cgic_reference' => $reference,
            'cgic_outcome_notes' => $notes,
            'cgic_decided_at' => CarbonImmutable::now(),
            'cgic_decided_by' => $actor->getKey(),
        ]);

        return $application;
    }
}

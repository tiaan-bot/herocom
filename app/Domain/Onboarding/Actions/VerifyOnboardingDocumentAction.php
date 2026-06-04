<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Actions;

use App\Domain\Onboarding\Enums\VerificationStatus;
use App\Domain\Onboarding\Models\OnboardingDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Access\Gate;

final class VerifyOnboardingDocumentAction
{
    public function __construct(
        private readonly Gate $gate,
    ) {}

    public function execute(
        OnboardingDocument $document,
        User $actor,
        VerificationStatus $status,
        ?string $notes = null,
    ): OnboardingDocument {
        $this->gate->forUser($actor)->authorize('process', $document->application);

        $document->update([
            'verification_status' => $status,
            'verified_by' => $actor->getKey(),
            'verified_at' => CarbonImmutable::now(),
            'verification_notes' => $notes,
        ]);

        return $document;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Actions;

use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Gate;

final class ViewCgicPayloadAction
{
    public function __construct(
        private readonly Gate $gate,
    ) {}

    /**
     * Decrypt and return the CGIC submission packet (banking, trade references,
     * legal disclosures), logging finance access to it.
     *
     * @return array<string, mixed>
     */
    public function execute(OnboardingApplication $application, User $actor): array
    {
        $this->gate->forUser($actor)->authorize('recordCgic', $application);

        activity('onboarding')
            ->causedBy($actor)
            ->performedOn($application)
            ->event('cgic_payload_accessed')
            ->log('Accessed CGIC submission packet');

        if (blank($application->cgic_payload)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $application->cgic_payload, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}

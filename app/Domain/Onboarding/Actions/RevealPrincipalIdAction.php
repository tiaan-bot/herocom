<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Actions;

use App\Domain\Onboarding\Models\OnboardingPrincipal;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Gate;

final class RevealPrincipalIdAction
{
    public function __construct(
        private readonly Gate $gate,
    ) {}

    /**
     * Decrypt and return a principal's SA ID number, logging who revealed it.
     */
    public function execute(OnboardingPrincipal $principal, User $actor): string
    {
        $this->gate->forUser($actor)->authorize('process', $principal->application);

        activity('onboarding')
            ->causedBy($actor)
            ->performedOn($principal)
            ->event('id_revealed')
            ->log('Revealed principal ID number');

        // The 'encrypted' cast decrypts on read; never log the value itself.
        return $principal->id_number;
    }
}

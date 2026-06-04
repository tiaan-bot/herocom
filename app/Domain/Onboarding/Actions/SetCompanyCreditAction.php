<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Actions;

use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Gate;

final class SetCompanyCreditAction
{
    public function __construct(
        private readonly Gate $gate,
    ) {}

    /**
     * Set a company's credit terms off the recorded CGIC outcome. Also used to
     * downgrade a company to eft_upfront (credit withdrawal → reverts to COD,
     * per T&Cs clause 7.6), which clears the limit and terms days.
     */
    public function execute(
        Company $company,
        User $actor,
        CreditTerms $terms,
        ?float $creditLimit = null,
        ?int $creditTermsDays = null,
    ): Company {
        $this->gate->forUser($actor)->authorize('manageCredit', $company);

        $isOnAccount = $terms === CreditTerms::OnAccount;

        $company->update([
            'credit_terms' => $terms,
            'credit_limit' => $isOnAccount ? $creditLimit : null,
            'credit_terms_days' => $isOnAccount ? $creditTermsDays : null,
        ]);

        return $company;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Policies;

use App\Domain\Onboarding\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function manageCredit(User $user, Company $company): bool
    {
        return $user->can('manage_company_credit');
    }
}

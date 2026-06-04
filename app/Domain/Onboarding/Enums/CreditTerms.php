<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Enums;

enum CreditTerms: string
{
    case EftUpfront = 'eft_upfront';
    case OnAccount = 'on_account';
}

<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Enums;

/**
 * How a trade reference account is held by the applicant.
 */
enum AccountHeld: string
{
    case Cod = 'cod';
    case Credit = 'credit';
}

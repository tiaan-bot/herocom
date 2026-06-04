<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Enums;

enum AccountType: string
{
    case Cod = 'cod';
    case Credit = 'credit';
}

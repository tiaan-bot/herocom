<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Enums;

enum EntityType: string
{
    case SoleProprietor = 'sole_proprietor';
    case Partnership = 'partnership';
    case Company = 'company';
    case CloseCorporation = 'close_corporation';
}

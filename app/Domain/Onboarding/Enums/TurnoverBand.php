<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Enums;

enum TurnoverBand: string
{
    case Under2m = 'under_2m';
    case Over2m = 'over_2m';
}

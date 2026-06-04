<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Enums;

enum CgicStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
}

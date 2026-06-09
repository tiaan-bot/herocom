<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum StaffStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case PendingInvite = 'pending_invite';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::PendingInvite => 'Pending invite',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'danger',
            self::PendingInvite => 'warning',
        };
    }
}

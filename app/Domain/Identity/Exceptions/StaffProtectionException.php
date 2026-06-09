<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

final class StaffProtectionException extends RuntimeException
{
    public static function cannotDeactivateSelf(): self
    {
        return new self('You cannot deactivate your own account.');
    }

    public static function cannotDeleteSelf(): self
    {
        return new self('You cannot delete your own account.');
    }

    public static function cannotDemoteSelf(): self
    {
        return new self('You cannot remove your own super admin role.');
    }

    public static function lastActiveSuperAdmin(): self
    {
        return new self('This is the last active super admin and cannot be deactivated, deleted, or demoted.');
    }
}

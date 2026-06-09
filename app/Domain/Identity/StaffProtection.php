<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Identity\Exceptions\StaffProtectionException;
use App\Models\User;

/**
 * Self-protection rules for staff management: a super admin may not lock
 * themselves out, and the platform must always retain at least one active
 * super admin.
 */
final class StaffProtection
{
    public function activeSuperAdminCount(): int
    {
        return User::query()->role(Roles::SUPER_ADMIN)->where('is_active', true)->count();
    }

    public function isLastActiveSuperAdmin(User $user): bool
    {
        return $user->is_active
            && $user->hasRole(Roles::SUPER_ADMIN)
            && $this->activeSuperAdminCount() <= 1;
    }

    public function assertCanDeactivate(User $target, User $actor): void
    {
        if ($target->is($actor)) {
            throw StaffProtectionException::cannotDeactivateSelf();
        }
        if ($this->isLastActiveSuperAdmin($target)) {
            throw StaffProtectionException::lastActiveSuperAdmin();
        }
    }

    public function assertCanDelete(User $target, User $actor): void
    {
        if ($target->is($actor)) {
            throw StaffProtectionException::cannotDeleteSelf();
        }
        if ($this->isLastActiveSuperAdmin($target)) {
            throw StaffProtectionException::lastActiveSuperAdmin();
        }
    }

    /**
     * @param  list<string>  $newRoleNames
     */
    public function assertRolesAllowed(User $target, User $actor, array $newRoleNames): void
    {
        $losesSuperAdmin = $target->hasRole(Roles::SUPER_ADMIN)
            && ! in_array(Roles::SUPER_ADMIN, $newRoleNames, true);

        if (! $losesSuperAdmin) {
            return;
        }

        if ($target->is($actor)) {
            throw StaffProtectionException::cannotDemoteSelf();
        }
        if ($this->isLastActiveSuperAdmin($target)) {
            throw StaffProtectionException::lastActiveSuperAdmin();
        }
    }
}

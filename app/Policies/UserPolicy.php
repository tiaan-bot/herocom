<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Roles;
use App\Domain\Identity\StaffProtection;
use App\Models\User;

/**
 * Staff management is super-admin only. (super_admin also passes via the
 * Gate::before bypass in AppServiceProvider, but this policy is explicit so the
 * resource's authorization reads clearly and non-super-admins are denied.)
 */
class UserPolicy
{
    public function __construct(private readonly StaffProtection $protection) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(Roles::SUPER_ADMIN);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasRole(Roles::SUPER_ADMIN);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(Roles::SUPER_ADMIN);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasRole(Roles::SUPER_ADMIN);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->hasRole(Roles::SUPER_ADMIN)
            && ! $target->is($actor)
            && ! $this->protection->isLastActiveSuperAdmin($target);
    }

    public function restore(User $actor, User $target): bool
    {
        return $actor->hasRole(Roles::SUPER_ADMIN);
    }

    public function forceDelete(User $actor, User $target): bool
    {
        return false;
    }
}

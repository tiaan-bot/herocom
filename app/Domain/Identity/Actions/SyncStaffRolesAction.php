<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Roles;
use App\Domain\Identity\StaffProtection;
use App\Models\User;

/**
 * Sync a staff member's internal roles (reseller tiers are filtered out),
 * guarded against self-demotion / demoting the last active super admin, and
 * audited when the role set changes.
 */
final class SyncStaffRolesAction
{
    public function __construct(private readonly StaffProtection $protection) {}

    /**
     * @param  list<string>  $roleNames
     */
    public function execute(User $target, array $roleNames, User $actor): void
    {
        $roles = array_values(array_filter($roleNames, Roles::isInternal(...)));

        $this->protection->assertRolesAllowed($target, $actor, $roles);

        $before = $target->getRoleNames()->sort()->values()->all();
        $target->syncRoles($roles);
        $after = collect($roles)->sort()->values()->all();

        if ($before !== $after) {
            activity('user')
                ->performedOn($target)
                ->causedBy($actor)
                ->withProperties(['from' => $before, 'to' => $after])
                ->event('roles_updated')
                ->log('roles_updated');
        }
    }
}

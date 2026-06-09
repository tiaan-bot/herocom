<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\StaffProtection;
use App\Models\User;

/**
 * Activate or deactivate a staff member. Deactivation is guarded (no self
 * lock-out, never the last active super admin) and audited via activitylog
 * (is_active is in the User log allowlist).
 */
final class SetStaffActiveStatusAction
{
    public function __construct(private readonly StaffProtection $protection) {}

    public function execute(User $target, bool $active, User $actor): void
    {
        if (! $active) {
            $this->protection->assertCanDeactivate($target, $actor);
        }

        $target->update(['is_active' => $active]);
    }
}

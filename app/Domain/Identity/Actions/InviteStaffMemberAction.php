<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Roles;
use App\Models\User;
use App\Notifications\StaffInviteNotification;
use Illuminate\Support\Str;

/**
 * Create an internal staff member in the Pending state — no usable password
 * (the 'hashed' cast hashes a random throwaway value) and password_set_at left
 * null — then email a signed set-password invite. Only internal roles are
 * assigned; reseller tiers are filtered out.
 */
final class InviteStaffMemberAction
{
    /**
     * @param  list<string>  $roleNames
     */
    public function execute(string $name, string $email, array $roleNames): User
    {
        $roles = array_values(array_filter($roleNames, Roles::isInternal(...)));

        $user = new User;
        $user->name = $name;
        $user->email = $email;
        // Unusable until the invitee sets one; password_set_at stays null = Pending.
        $user->password = Str::random(40);
        $user->is_active = true;
        $user->save();

        $user->syncRoles($roles);

        $user->notify(new StaffInviteNotification);

        return $user;
    }
}

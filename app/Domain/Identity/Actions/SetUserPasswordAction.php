<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Sets a user's password (the 'hashed' cast hashes it), stamps password_set_at
 * — which makes the one-time set-password link single-purpose — and rotates the
 * remember token. Used by both the initial set-password flow and password reset.
 */
final class SetUserPasswordAction
{
    public function execute(User $user, string $plainPassword): User
    {
        $user->forceFill([
            'password' => $plainPassword,
            'password_set_at' => CarbonImmutable::now(),
            'remember_token' => Str::random(60),
        ])->save();

        return $user;
    }
}

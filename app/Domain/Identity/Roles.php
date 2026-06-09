<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Canonical role names (spatie/laravel-permission). The Staff resource only ever
 * manages internal roles; reseller-tier roles are never assignable there.
 */
final class Roles
{
    public const SUPER_ADMIN = 'super_admin';

    /** @var list<string> The six internal Herocom staff roles. */
    public const INTERNAL = [
        'super_admin',
        'sales_admin',
        'finance_admin',
        'warranty_admin',
        'support_agent',
        'viewer',
    ];

    /** @var list<string> The three reseller (external) tiers. */
    public const RESELLER = [
        'reseller_owner',
        'reseller_buyer',
        'reseller_viewer',
    ];

    /**
     * Internal roles as Filament select options (value => human label).
     *
     * @return array<string, string>
     */
    public static function internalOptions(): array
    {
        $options = [];
        foreach (self::INTERNAL as $role) {
            $options[$role] = (string) str($role)->headline();
        }

        return $options;
    }

    public static function isInternal(string $role): bool
    {
        return in_array($role, self::INTERNAL, true);
    }
}

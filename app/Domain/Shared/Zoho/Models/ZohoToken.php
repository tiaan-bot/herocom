<?php

declare(strict_types=1);

namespace App\Domain\Shared\Zoho\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Single-row OAuth token store for the Herocom Zoho org. Tokens are encrypted
 * at rest and hidden from serialization — never log them.
 *
 * @property string $refresh_token
 * @property string|null $access_token
 * @property Carbon|null $access_token_expires_at
 * @property array<int, string>|null $scopes
 */
class ZohoToken extends Model
{
    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'refresh_token',
        'access_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'scopes' => 'array',
        ];
    }

    public function hasValidAccessToken(): bool
    {
        return filled($this->access_token)
            && $this->access_token_expires_at !== null
            && $this->access_token_expires_at->isFuture();
    }
}

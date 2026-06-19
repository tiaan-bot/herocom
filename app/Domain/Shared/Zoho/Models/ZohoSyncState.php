<?php

declare(strict_types=1);

namespace App\Domain\Shared\Zoho\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-entity incremental sync cursor (one row per sync key, e.g. "products").
 * The cursor is a true UTC instant — the high-water mark of items that have been
 * fully processed. It is advanced only after a changed set is completely
 * paginated, so a timeout or mid-run error never skips unprocessed items.
 *
 * @property string $key
 * @property Carbon|null $last_modified_cursor
 */
class ZohoSyncState extends Model
{
    protected $fillable = [
        'key',
        'last_modified_cursor',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_modified_cursor' => 'datetime',
        ];
    }
}

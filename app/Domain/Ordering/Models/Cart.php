<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Onboarding\Models\Company;
use App\Domain\Ordering\Enums\CartStatus;
use App\Domain\Shared\Concerns\HasUuid;
use App\Models\User;
use Database\Factories\Ordering\CartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $uuid
 * @property int $user_id
 * @property int $company_id
 * @property CartStatus $status
 */
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    use HasUuid;

    protected $fillable = [
        'user_id',
        'company_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
        ];
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function newFactory(): CartFactory
    {
        return CartFactory::new();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Onboarding\Models\Company;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\ZohoPushStatus;
use App\Domain\Shared\Concerns\HasUuid;
use App\Models\User;
use Database\Factories\Ordering\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $uuid
 * @property string|null $order_number
 * @property int $company_id
 * @property OrderStatus $status
 * @property ZohoPushStatus $zoho_push_status
 * @property string|null $zoho_salesorder_id
 * @property string|null $customer_note
 * @property-read Company $company
 * @property-read Collection<int, OrderItem> $items
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'zoho_push_status' => ZohoPushStatus::class,
            'subtotal_ex_vat' => 'decimal:4',
            'discount_percent_applied' => 'decimal:2',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'zoho_pushed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopeForCompany(Builder $query, int $companyId): void
    {
        $query->where('company_id', $companyId);
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}

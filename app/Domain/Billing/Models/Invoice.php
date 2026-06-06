<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Ordering\Models\Order;
use App\Domain\Shared\Concerns\HasUuid;
use Database\Factories\Billing\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $uuid
 * @property int $company_id
 * @property int|null $order_id
 * @property string $zoho_invoice_id
 * @property string $invoice_number
 * @property InvoiceStatus $status
 * @property \Illuminate\Support\Carbon $invoice_date
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string $subtotal_ex_vat
 * @property string $tax_total
 * @property string $total
 * @property string $balance
 * @property string $currency
 * @property string|null $payment_url
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal_ex_vat' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'balance' => 'decimal:4',
            'zoho_last_modified_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Portal-visible invoices: everything except drafts.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', '!=', InvoiceStatus::Draft);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }
}

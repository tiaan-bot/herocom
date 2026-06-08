<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Models;

use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Enums\EntityType;
use App\Domain\Shared\Concerns\HasUuid;
use App\Models\User;
use Database\Factories\Onboarding\CompanyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $uuid
 * @property EntityType $entity_type
 * @property CompanyStatus $status
 * @property CreditTerms $credit_terms
 * @property string|null $zoho_customer_id
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    use HasUuid;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'legal_name',
        'trading_name',
        'entity_type',
        'registration_number',
        'date_of_registration',
        'vat_number',
        'nature_of_business',
        'telephone',
        'fax',
        'postal_address_line1',
        'postal_province',
        'postal_postal_code',
        'status',
        'credit_terms',
        'credit_limit',
        'credit_limit_currency',
        'credit_terms_days',
        'discount_percent',
        'address_line1',
        'address_line2',
        'city',
        'province',
        'postal_code',
        'country_code',
        'currency',
        'zoho_customer_id',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'suspended_at',
        'suspended_by',
        'suspension_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => EntityType::class,
            'status' => CompanyStatus::class,
            'credit_terms' => CreditTerms::class,
            'date_of_registration' => 'date',
            'credit_limit' => 'decimal:4',
            'credit_terms_days' => 'integer',
            'discount_percent' => 'decimal:2',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OnboardingApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(OnboardingApplication::class);
    }

    /**
     * Cross-domain read relation: a Company has many reseller Users.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    /**
     * @param  Builder<Company>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', CompanyStatus::Approved);
    }

    /**
     * @param  Builder<Company>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', CompanyStatus::Pending);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Company holds no high-sensitivity PII, but log only the material business fields.
        return LogOptions::defaults()
            ->useLogName('company')
            ->logOnly([
                'legal_name',
                'trading_name',
                'status',
                'credit_terms',
                'credit_limit',
                'credit_terms_days',
                'discount_percent',
                'zoho_customer_id',
                'approved_by',
                'rejected_by',
                'suspended_by',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}

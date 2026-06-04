<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Models;

use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Enums\TurnoverBand;
use App\Domain\Shared\Concerns\HasUuid;
use App\Models\User;
use Database\Factories\Onboarding\OnboardingApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $uuid
 * @property int $company_id
 * @property AccountType $account_type_requested
 * @property ApplicationStatus $status
 * @property string $contact_name
 * @property string $contact_email
 * @property CgicStatus $cgic_status
 * @property TurnoverBand|null $annual_turnover_band
 * @property-read Company $company
 */
class OnboardingApplication extends Model
{
    /** @use HasFactory<OnboardingApplicationFactory> */
    use HasFactory;

    use HasUuid;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'account_type_requested',
        'status',
        'contact_name',
        'contact_email',
        'contact_phone',
        'premises_owned',
        'landlord_name',
        'landlord_address',
        'landlord_tel',
        'period_at_address',
        'credit_limit_requested',
        'credit_limit_requested_currency',
        'credit_terms_requested_days',
        'annual_turnover_band',
        'cgic_payload',
        'cgic_status',
        'cgic_reference',
        'cgic_outcome_notes',
        'cgic_decided_at',
        'cgic_decided_by',
        'terms_accepted_at',
        'terms_version',
        'popia_consent_at',
        'credit_enquiry_consent_at',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'decision_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type_requested' => AccountType::class,
            'status' => ApplicationStatus::class,
            'cgic_status' => CgicStatus::class,
            'annual_turnover_band' => TurnoverBand::class,
            'premises_owned' => 'boolean',
            'credit_limit_requested' => 'decimal:4',
            'credit_terms_requested_days' => 'integer',
            // CGIC submission packet — encrypted at rest, opaque/non-queryable by design.
            'cgic_payload' => 'encrypted',
            'cgic_decided_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'popia_consent_at' => 'datetime',
            'credit_enquiry_consent_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @var list<string>
     */
    protected $hidden = [
        'cgic_payload',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<OnboardingPrincipal, $this>
     */
    public function principals(): HasMany
    {
        return $this->hasMany(OnboardingPrincipal::class);
    }

    /**
     * @return HasMany<OnboardingDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(OnboardingDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cgicDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cgic_decided_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        // NEVER log cgic_payload — it carries banking details and legal disclosures.
        return LogOptions::defaults()
            ->useLogName('onboarding_application')
            ->logOnly([
                'account_type_requested',
                'status',
                'cgic_status',
                'cgic_reference',
                'cgic_decided_by',
                'reviewed_by',
                'terms_version',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function newFactory(): OnboardingApplicationFactory
    {
        return OnboardingApplicationFactory::new();
    }
}

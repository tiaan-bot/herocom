<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Models;

use Database\Factories\Onboarding\OnboardingPrincipalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class OnboardingPrincipal extends Model
{
    /** @use HasFactory<OnboardingPrincipalFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'onboarding_application_id',
        'full_name',
        'surname',
        'id_number',
        'shareholding_percent',
        'residential_address_line1',
        'residential_address_line2',
        'residential_city',
        'residential_province',
        'residential_postal_code',
        'country_code',
        'is_surety',
        'married_in_community',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // SA ID number — sensitive PII, encrypted at rest.
            'id_number' => 'encrypted',
            'shareholding_percent' => 'decimal:2',
            'is_surety' => 'boolean',
            'married_in_community' => 'boolean',
        ];
    }

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id_number',
    ];

    /**
     * @return BelongsTo<OnboardingApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(OnboardingApplication::class, 'onboarding_application_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        // NEVER log id_number. Residential address changes may be audited.
        return LogOptions::defaults()
            ->useLogName('onboarding_principal')
            ->logOnly([
                'full_name',
                'surname',
                'shareholding_percent',
                'is_surety',
                'married_in_community',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function newFactory(): OnboardingPrincipalFactory
    {
        return OnboardingPrincipalFactory::new();
    }
}

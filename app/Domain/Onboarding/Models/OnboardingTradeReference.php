<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Models;

use App\Domain\Onboarding\Enums\AccountHeld;
use Database\Factories\Onboarding\OnboardingTradeReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $company_name
 * @property string $credit_limit
 * @property AccountHeld $account_held
 * @property int|null $terms_days
 */
class OnboardingTradeReference extends Model
{
    /** @use HasFactory<OnboardingTradeReferenceFactory> */
    use HasFactory;

    protected $fillable = [
        'onboarding_application_id',
        'company_name',
        'credit_limit',
        'credit_limit_currency',
        'account_held',
        'terms_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:4',
            'account_held' => AccountHeld::class,
            'terms_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<OnboardingApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(OnboardingApplication::class, 'onboarding_application_id');
    }

    protected static function newFactory(): OnboardingTradeReferenceFactory
    {
        return OnboardingTradeReferenceFactory::new();
    }
}

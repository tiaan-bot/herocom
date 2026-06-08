<?php

declare(strict_types=1);

namespace Database\Factories\Onboarding;

use App\Domain\Onboarding\Enums\AccountHeld;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Onboarding\Models\OnboardingTradeReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingTradeReference>
 */
class OnboardingTradeReferenceFactory extends Factory
{
    protected $model = OnboardingTradeReference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'onboarding_application_id' => OnboardingApplication::factory()->credit(),
            'company_name' => $this->faker->company(),
            'credit_limit' => $this->faker->numberBetween(10000, 200000),
            'credit_limit_currency' => 'ZAR',
            'account_held' => AccountHeld::Credit,
            'terms_days' => 30,
        ];
    }
}

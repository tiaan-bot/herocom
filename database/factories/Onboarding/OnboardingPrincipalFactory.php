<?php

declare(strict_types=1);

namespace Database\Factories\Onboarding;

use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Onboarding\Models\OnboardingPrincipal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingPrincipal>
 */
class OnboardingPrincipalFactory extends Factory
{
    protected $model = OnboardingPrincipal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'onboarding_application_id' => OnboardingApplication::factory(),
            'full_name' => $this->faker->firstName(),
            'surname' => $this->faker->lastName(),
            'id_number' => $this->faker->numerify('#############'), // 13-digit SA ID
            'shareholding_percent' => 100,
            'residential_address_line1' => $this->faker->streetAddress(),
            'residential_city' => $this->faker->city(),
            'residential_province' => 'Gauteng',
            'residential_postal_code' => $this->faker->numerify('####'),
            'country_code' => 'ZA',
            'is_surety' => true,
            'married_in_community' => false,
        ];
    }
}

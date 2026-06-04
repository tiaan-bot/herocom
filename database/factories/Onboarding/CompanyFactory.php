<?php

declare(strict_types=1);

namespace Database\Factories\Onboarding;

use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Enums\EntityType;
use App\Domain\Onboarding\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_name' => $this->faker->company().' (Pty) Ltd',
            'trading_name' => $this->faker->companySuffix(),
            'entity_type' => EntityType::Company,
            'registration_number' => $this->faker->numerify('####/######/07'),
            'vat_number' => $this->faker->numerify('4#########'),
            'nature_of_business' => $this->faker->bs(),
            'status' => CompanyStatus::Pending,
            'credit_terms' => CreditTerms::EftUpfront,
            'discount_percent' => 0,
            'address_line1' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'province' => 'Gauteng',
            'postal_code' => $this->faker->numerify('####'),
            'country_code' => 'ZA',
            'currency' => 'ZAR',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => CompanyStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function onAccount(): static
    {
        return $this->state(fn (): array => [
            'credit_terms' => CreditTerms::OnAccount,
            'credit_limit' => 50000,
            'credit_limit_currency' => 'ZAR',
            'credit_terms_days' => 30,
        ]);
    }
}

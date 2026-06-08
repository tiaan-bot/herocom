<?php

declare(strict_types=1);

namespace Database\Factories\Onboarding;

use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Enums\TurnoverBand;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingApplication>
 */
class OnboardingApplicationFactory extends Factory
{
    protected $model = OnboardingApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'account_type_requested' => AccountType::Cod,
            'status' => ApplicationStatus::Submitted,
            'contact_name' => $this->faker->name(),
            'contact_email' => $this->faker->safeEmail(),
            'contact_phone' => $this->faker->numerify('0##########'),
            'premises_owned' => true,
            'cgic_status' => CgicStatus::NotRequired,
            'terms_accepted_at' => now(),
            'terms_version' => '2026-01',
            'popia_consent_at' => now(),
            'submitted_at' => now(),
        ];
    }

    /**
     * The credit branch: collects credit requirements, turnover, and the encrypted CGIC packet.
     */
    public function credit(): static
    {
        return $this->state(fn (): array => [
            'account_type_requested' => AccountType::Credit,
            'cgic_status' => CgicStatus::Pending,
            'credit_limit_requested' => 50000,
            'credit_limit_requested_currency' => 'ZAR',
            'credit_terms_requested_days' => 30,
            'annual_turnover_band' => TurnoverBand::Under2m,
            'credit_enquiry_consent_at' => now(),
            'account_contact_name' => 'Account Contact',
            'account_contact_email' => 'accounts@demo.test',
            'account_contact_phone' => '0110000001',
            'cgic_payload' => json_encode([
                'banking' => [
                    'bank' => 'Demo Bank',
                    'date_opened' => '2020-01-15',
                    'branch_name' => 'Demo Branch',
                    'branch_code' => '250655',
                    'account_type' => 'cheque',
                    'account_number' => '1234567890',
                    'account_name' => 'Demo Trading',
                ],
                'disclosures' => ['judgements' => false, 'liquidations' => false, 'sureties_cessions' => false, 'moratoriums' => false],
            ]),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => ApplicationStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }
}

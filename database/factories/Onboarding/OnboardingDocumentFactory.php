<?php

declare(strict_types=1);

namespace Database\Factories\Onboarding;

use App\Domain\Onboarding\Enums\DocumentType;
use App\Domain\Onboarding\Enums\VerificationStatus;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Onboarding\Models\OnboardingDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingDocument>
 */
class OnboardingDocumentFactory extends Factory
{
    protected $model = OnboardingDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'onboarding_application_id' => OnboardingApplication::factory(),
            'type' => DocumentType::SignedApplicationForm,
            'disk' => 'r2',
            'path' => 'onboarding/'.$this->faker->uuid().'.pdf',
            'original_filename' => $this->faker->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(10_000, 5_000_000),
            'verification_status' => VerificationStatus::Pending,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
        ]);
    }

    public function ofType(DocumentType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}

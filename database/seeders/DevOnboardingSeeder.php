<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Enums\DocumentType;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Onboarding\Models\OnboardingDocument;
use App\Domain\Onboarding\Models\OnboardingPrincipal;
use Illuminate\Database\Seeder;

/**
 * Dev-only sample onboarding applications to exercise the /admin approval workflow.
 *
 * NOT wired into DatabaseSeeder — run explicitly:
 *   php artisan db:seed --class=DevOnboardingSeeder
 */
class DevOnboardingSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('DevOnboardingSeeder is dev-only — skipped in production.');

            return;
        }

        // 1. COD application — submitted, ready to approve immediately (no CGIC).
        $cod = OnboardingApplication::factory()
            ->for(Company::factory()->state(['legal_name' => 'Cape Town Cellular (Pty) Ltd']))
            ->create([
                'contact_name' => 'Thandi Mokoena',
                'contact_email' => 'thandi@ctcellular.test',
            ]);
        $this->attachDocuments($cod, [
            DocumentType::SignedApplicationForm,
            DocumentType::IdDocument,
            DocumentType::CipcRegistration,
        ]);

        // 2. Credit application — CGIC pending, with sureties. Approve stays blocked
        //    until finance records a CGIC approval.
        $creditPending = OnboardingApplication::factory()
            ->credit()
            ->for(Company::factory()->state(['legal_name' => 'Durban Distributors CC']))
            ->has(OnboardingPrincipal::factory()->count(2), 'principals')
            ->create([
                'contact_name' => 'Sipho Ndlovu',
                'contact_email' => 'sipho@durbandist.test',
            ]);
        $this->attachDocuments($creditPending, [
            DocumentType::SignedApplicationForm,
            DocumentType::IdDocument,
            DocumentType::CipcRegistration,
            DocumentType::BankConfirmation,
            DocumentType::ProofOfAddress,
            DocumentType::DeedOfSurety,
        ]);

        // 3. Credit application — CGIC already approved, so Approve is enabled.
        $creditApproved = OnboardingApplication::factory()
            ->credit()
            ->for(Company::factory()->state(['legal_name' => 'Joburg Gadgets (Pty) Ltd']))
            ->has(OnboardingPrincipal::factory()->count(1), 'principals')
            ->create([
                'cgic_status' => CgicStatus::Approved,
                'cgic_reference' => 'CGIC-DEV-001',
                'contact_name' => 'Lerato Dlamini',
                'contact_email' => 'lerato@joburggadgets.test',
            ]);
        $this->attachDocuments($creditApproved, [
            DocumentType::SignedApplicationForm,
            DocumentType::IdDocument,
            DocumentType::BankConfirmation,
            DocumentType::ProofOfAddress,
            DocumentType::DeedOfSurety,
        ]);

        $this->command->info('Seeded 3 onboarding applications: 1 COD (submitted), 1 credit (CGIC pending), 1 credit (CGIC approved).');
    }

    /**
     * @param  list<DocumentType>  $types
     */
    private function attachDocuments(OnboardingApplication $application, array $types): void
    {
        foreach ($types as $type) {
            OnboardingDocument::factory()->ofType($type)->for($application, 'application')->create();
        }
    }
}

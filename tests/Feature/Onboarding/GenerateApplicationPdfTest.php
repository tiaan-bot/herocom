<?php

declare(strict_types=1);

use App\Domain\Onboarding\Enums\ApplicationPdfStatus;
use App\Domain\Onboarding\Enums\DocumentType;
use App\Domain\Onboarding\Jobs\GenerateApplicationPdf;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Onboarding\Models\OnboardingDocument;
use App\Domain\Onboarding\Models\OnboardingPrincipal;
use App\Filament\Resources\OnboardingApplications\Pages\ViewOnboardingApplication;
use App\Filament\Resources\OnboardingApplications\RelationManagers\DocumentsRelationManager;
use App\Notifications\ApplicationReceivedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['onboarding.documents.disk' => 'r2']);
    Storage::fake('r2');
});

it('generates the application-form pdf, records the document, and marks it generated', function () {
    Notification::fake();
    $application = OnboardingApplication::factory()->create([
        'signed_by_name' => 'Jane Doe',
        'signed_by_capacity' => 'Director',
        'signed_at' => now(),
    ]);

    GenerateApplicationPdf::dispatchSync($application);

    $document = $application->documents()->where('type', DocumentType::ApplicationForm)->first();

    expect($document)->not->toBeNull()
        ->and($document->mime_type)->toBe('application/pdf')
        ->and($document->path)->toBe("onboarding/{$application->uuid}/application-form.pdf")
        ->and($application->fresh()->application_pdf_status)->toBe(ApplicationPdfStatus::Generated);

    Storage::disk('r2')->assertExists($document->path);
    Notification::assertSentOnDemand(ApplicationReceivedNotification::class);
});

it('replaces the existing application-form pdf when regenerated', function () {
    $application = OnboardingApplication::factory()->create();

    GenerateApplicationPdf::dispatchSync($application);
    GenerateApplicationPdf::dispatchSync($application);

    expect($application->documents()->where('type', DocumentType::ApplicationForm)->count())->toBe(1);
});

it('renders the credit application form with the key fields', function () {
    $company = Company::factory()->create([
        'legal_name' => 'Acme Trading (Pty) Ltd',
        'registration_number' => '2019/123456/07',
    ]);
    $application = OnboardingApplication::factory()->credit()->create([
        'company_id' => $company->id,
        'signed_by_name' => 'Jane Doe',
        'signed_by_capacity' => 'Director',
    ]);
    OnboardingPrincipal::factory()->create([
        'onboarding_application_id' => $application->id,
        'full_name' => 'Jane',
        'surname' => 'Doe',
        'id_number' => '9001015800086',
    ]);

    $html = view('pdf.application-credit', [
        'application' => $application->load(['company', 'principals']),
        'wordmark' => null,
        'signature' => null,
    ])->render();

    expect($html)
        ->toContain('Acme Trading (Pty) Ltd')   // company name
        ->toContain('2019/123456/07')            // registration number
        ->toContain('Jane Doe')                  // signatory
        ->toContain('Credit');                   // account type
});

it('appends the standard terms & conditions on both templates, on a fresh page, version-tagged', function () {
    $cod = OnboardingApplication::factory()->create();
    $credit = OnboardingApplication::factory()->credit()->create();

    foreach (['pdf.application-cod' => $cod, 'pdf.application-credit' => $credit] as $view => $application) {
        $html = view($view, [
            'application' => $application->load(['company', 'principals']),
            'wordmark' => null,
            'signature' => null,
        ])->render();

        expect($html)
            ->toContain('class="terms"')                       // wrapper that page-breaks before
            ->toContain('Standard Terms')                      // heading
            ->toContain('Version 2026-01')                     // version tag matches the declaration
            ->toContain('Retention of title')                  // a numbered clause is present
            ->toContain('revert to the COD Sale Agreement');  // clause 7.6 preserved
    }
});

it('lists the application_form document in the Filament documents section', function () {
    $this->seed(RolePermissionSeeder::class);
    $application = OnboardingApplication::factory()
        ->has(OnboardingDocument::factory()->ofType(DocumentType::ApplicationForm)->state(['disk' => 'r2']), 'documents')
        ->create();
    $document = $application->documents->first();
    $this->actingAs(userWithRole('sales_admin'));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewOnboardingApplication::class,
    ])->assertCanSeeTableRecords([$document]);
});

it('queues a regeneration from the Filament action', function () {
    $this->seed(RolePermissionSeeder::class);
    Queue::fake();
    $application = OnboardingApplication::factory()->create(['application_pdf_status' => ApplicationPdfStatus::Failed]);
    $this->actingAs(userWithRole('sales_admin'));

    Livewire::test(ViewOnboardingApplication::class, ['record' => $application->getRouteKey()])
        ->callAction('regeneratePdf');

    Queue::assertPushed(GenerateApplicationPdf::class);
    expect($application->fresh()->application_pdf_status)->toBe(ApplicationPdfStatus::Pending);
});

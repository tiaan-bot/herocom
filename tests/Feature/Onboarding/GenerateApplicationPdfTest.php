<?php

declare(strict_types=1);

use App\Domain\Onboarding\Enums\AccountHeld;
use App\Domain\Onboarding\Enums\ApplicationPdfStatus;
use App\Domain\Onboarding\Enums\DocumentType;
use App\Domain\Onboarding\Jobs\GenerateApplicationPdf;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Onboarding\Models\OnboardingDocument;
use App\Domain\Onboarding\Models\OnboardingPrincipal;
use App\Domain\Onboarding\Models\OnboardingTradeReference;
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

it('renders the COD application with all company sections, consent and signatories', function () {
    $company = Company::factory()->create([
        'legal_name' => 'Acme Trading (Pty) Ltd',
        'registration_number' => '2019/123456/07',
        'vat_number' => '4123456789',
    ]);
    $application = OnboardingApplication::factory()->create([
        'company_id' => $company->id,
        'signed_by_name' => 'Jane Doe',
        'signed_by_capacity' => 'Director',
    ]);

    $html = view('pdf.application-cod', [
        'application' => $application->load(['company', 'principals']),
        'wordmark' => null,
        'signature' => null,
    ])->render();

    expect($html)
        ->toContain('Acme Trading (Pty) Ltd')
        ->toContain('4123456789')
        ->toContain('Registered name')
        ->toContain('Date of registration')   // gap field still labelled
        ->toContain('Postal address')         // gap field still labelled
        ->toContain('Premises owned')
        ->toContain('Consent')
        ->toContain('Signatories')
        ->toContain('Jane Doe');
});

it('renders the credit application with every captured section populated', function () {
    $company = Company::factory()->create([
        'legal_name' => 'Acme Trading (Pty) Ltd',
        'registration_number' => '2019/123456/07',
        'date_of_registration' => '2018-03-04',
        'telephone' => '011 555 0100',
        'postal_address_line1' => 'PO Box 42',
        'postal_province' => 'Gauteng',
        'postal_postal_code' => '2001',
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
        'shareholding_percent' => 100,
    ]);
    OnboardingTradeReference::factory()->create([
        'onboarding_application_id' => $application->id,
        'company_name' => 'Supplier A',
        'account_held' => AccountHeld::Credit,
        'terms_days' => 30,
    ]);

    $html = view('pdf.application-credit', [
        'application' => $application->load(['company', 'principals', 'tradeReferences']),
        'wordmark' => null,
        'signature' => null,
    ])->render();

    expect($html)
        ->toContain('Acme Trading (Pty) Ltd')                       // company
        ->toContain('04 March 2018')                                // date of registration
        ->toContain('PO Box 42')                                    // postal address
        ->toContain('011 555 0100')                                 // company telephone
        ->toContain('Proprietors / directors / members / partners') // principals section
        ->toContain('9001015800086')                                // principal ID
        ->toContain('Banking details')
        ->toContain('Demo Bank')                                    // banking institution
        ->toContain('Demo Branch')                                  // branch name (now captured)
        ->toContain('Cheque')                                       // account type (now captured)
        ->toContain('Supplier A')                                   // trade reference (now captured)
        ->toContain('Credit requirements')
        ->toContain('Less than R2,000,000')                         // turnover band mapped
        ->toContain('Security / legal compliance')
        ->toContain('Account contact')                              // account contact section
        ->toContain('Signatories');
});

it('renders the verbatim consent blocks before the signatories, with no reconstructed notes', function () {
    $cod = OnboardingApplication::factory()->create();
    $credit = OnboardingApplication::factory()->credit()->create();

    $codHtml = view('pdf.application-cod', [
        'application' => $cod->load(['company', 'principals']), 'wordmark' => null, 'signature' => null,
    ])->render();
    $creditHtml = view('pdf.application-credit', [
        'application' => $credit->load(['company', 'principals', 'tradeReferences']), 'wordmark' => null, 'signature' => null,
    ])->render();

    // COD consent (verbatim).
    expect($codHtml)
        ->toContain('I/We warrant that all information provided in this application is true and correct.')
        ->toContain('signed of my/our own free will')
        ->not->toContain('reconstructed');

    // Credit consent (verbatim) incl. RIA bullets + the 2.36% interest clause.
    expect($creditHtml)
        ->toContain('I/We warrant that all information provided in this application is true and correct.')
        ->toContain('Protection of Personal Information Act (POPI)')
        ->toContain('May access the database of any Risk Information Agency prior to granting credit;')
        ->toContain('Interest at 2.36% will be applied to finance accounts after 30 days;')
        ->toContain('May record the existence of the Customer')
        ->not->toContain('reconstructed');

    // Consent sits before the terms (which end the document, after the signatories).
    expect(strpos($creditHtml, 'Risk Information Agency'))->toBeLessThan(strpos($creditHtml, 'Signatories'));
});

it('appends the verbatim standard terms & conditions on both templates, on a fresh page', function () {
    $cod = OnboardingApplication::factory()->create();
    $credit = OnboardingApplication::factory()->credit()->create();

    foreach (['pdf.application-cod' => $cod, 'pdf.application-credit' => $credit] as $view => $application) {
        $html = view($view, [
            'application' => $application->load(['company', 'principals']),
            'wordmark' => null,
            'signature' => null,
        ])->render();

        expect($html)
            ->toContain('class="terms"')                                 // page-breaks before
            ->toContain('Standard Terms')                                // heading
            ->toContain('Version 2026-01')                               // version tag
            ->toContain('1. INTERPRETATION AND DEFINITIONS')             // first clause (verbatim)
            ->toContain('18. NOTICES AND DOMICILIA')                     // last clause (all 18 present)
            ->toContain('Regulation 19(4) of the National Credit Act')   // verbatim clause 6
            ->toContain('voetstoots')                                    // verbatim clause 10/11
            ->toContain("Magistrates' Court Act 32 of 1944");            // verbatim clause 17
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

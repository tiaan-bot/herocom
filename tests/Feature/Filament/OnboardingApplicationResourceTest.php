<?php

declare(strict_types=1);

use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Enums\VerificationStatus;
use App\Domain\Onboarding\Events\CompanyApproved;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Onboarding\Models\OnboardingDocument;
use App\Domain\Onboarding\Models\OnboardingPrincipal;
use App\Filament\Resources\OnboardingApplications\Pages\ViewOnboardingApplication;
use App\Filament\Resources\OnboardingApplications\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\OnboardingApplications\RelationManagers\PrincipalsRelationManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function viewApplication(OnboardingApplication $application): Testable
{
    return Livewire::test(ViewOnboardingApplication::class, ['record' => $application->getRouteKey()]);
}

it('shows the review/approve actions to sales but not the finance CGIC action', function () {
    $this->actingAs(userWithRole('sales_admin'));

    viewApplication(OnboardingApplication::factory()->create())
        ->assertActionVisible('approve')
        ->assertActionVisible('reject')
        ->assertActionVisible('requestInfo');
});

it('shows CGIC actions to finance on a credit app but hides approve', function () {
    $this->actingAs(userWithRole('finance_admin'));

    viewApplication(OnboardingApplication::factory()->credit()->create())
        ->assertActionVisible('recordCgic')
        ->assertActionVisible('viewCgicPayload')
        ->assertActionHidden('approve');
});

it('hides every action from a viewer', function () {
    $this->actingAs(userWithRole('viewer'));

    viewApplication(OnboardingApplication::factory()->credit()->create())
        ->assertActionHidden('approve')
        ->assertActionHidden('reject')
        ->assertActionHidden('requestInfo')
        ->assertActionHidden('recordCgic');
});

it('disables approve for a credit app until CGIC is approved', function () {
    $this->actingAs(userWithRole('sales_admin'));

    viewApplication(OnboardingApplication::factory()->credit()->create())
        ->assertActionDisabled('approve');

    viewApplication(OnboardingApplication::factory()->credit()->create(['cgic_status' => CgicStatus::Approved]))
        ->assertActionEnabled('approve');
});

it('approves a COD application through the action and fires CompanyApproved', function () {
    Event::fake([CompanyApproved::class]);
    $application = OnboardingApplication::factory()->create();
    $this->actingAs(userWithRole('sales_admin'));

    viewApplication($application)->callAction('approve');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Approved);
    Event::assertDispatched(CompanyApproved::class);
});

it('records a CGIC outcome through the finance action', function () {
    $application = OnboardingApplication::factory()->credit()->create();
    $this->actingAs(userWithRole('finance_admin'));

    viewApplication($application)->callAction('recordCgic', data: [
        'cgic_status' => CgicStatus::Approved->value,
        'cgic_reference' => 'CG-1',
    ]);

    expect($application->fresh()->cgic_status)->toBe(CgicStatus::Approved);
});

it('verifies a document via the relation manager', function () {
    $application = OnboardingApplication::factory()->has(OnboardingDocument::factory(), 'documents')->create();
    $document = $application->documents->first();
    $this->actingAs(userWithRole('sales_admin'));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewOnboardingApplication::class,
    ])->callAction(TestAction::make('verify')->table($document));

    expect($document->fresh()->verification_status)->toBe(VerificationStatus::Verified);
});

it('reveals a principal id via the relation manager and audits it', function () {
    $application = OnboardingApplication::factory()->credit()
        ->has(OnboardingPrincipal::factory(), 'principals')->create();
    $principal = $application->principals->first();
    $this->actingAs(userWithRole('sales_admin'));

    Livewire::test(PrincipalsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewOnboardingApplication::class,
    ])->callAction(TestAction::make('revealId')->table($principal));

    expect(Activity::where('event', 'id_revealed')->where('subject_id', $principal->id)->exists())->toBeTrue();
});

it('generates a signed url and audits a document download via the relation manager', function () {
    Storage::fake('r2');
    Storage::disk('r2')->buildTemporaryUrlsUsing(fn (string $path, $expiry): string => 'https://signed.test/'.$path);

    $application = OnboardingApplication::factory()
        ->has(OnboardingDocument::factory()->state(['disk' => 'r2']), 'documents')->create();
    $document = $application->documents->first();
    $this->actingAs(userWithRole('sales_admin'));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => ViewOnboardingApplication::class,
    ])->callAction(TestAction::make('download')->table($document));

    expect(Activity::where('event', 'document_accessed')->where('subject_id', $document->id)->exists())->toBeTrue();
});

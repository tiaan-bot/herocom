<?php

declare(strict_types=1);

use App\Domain\Onboarding\Actions\GenerateOnboardingDocumentUrlAction;
use App\Domain\Onboarding\Actions\RecordCgicOutcomeAction;
use App\Domain\Onboarding\Actions\RequestApplicationInformationAction;
use App\Domain\Onboarding\Actions\RevealPrincipalIdAction;
use App\Domain\Onboarding\Actions\VerifyOnboardingDocumentAction;
use App\Domain\Onboarding\Actions\ViewCgicPayloadAction;
use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Enums\VerificationStatus;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Onboarding\Models\OnboardingDocument;
use App\Domain\Onboarding\Models\OnboardingPrincipal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('verifies a document and records the reviewer', function () {
    $document = OnboardingDocument::factory()->create();

    app(VerifyOnboardingDocumentAction::class)
        ->execute($document, userWithRole('sales_admin'), VerificationStatus::Verified, 'looks legit');

    $document->refresh();
    expect($document->verification_status)->toBe(VerificationStatus::Verified)
        ->and($document->verified_by)->not->toBeNull()
        ->and($document->verification_notes)->toBe('looks legit');
});

it('denies document verification without the process ability', function () {
    $document = OnboardingDocument::factory()->create();

    expect(fn () => app(VerifyOnboardingDocumentAction::class)
        ->execute($document, userWithRole('finance_admin'), VerificationStatus::Verified))
        ->toThrow(AuthorizationException::class);
});

it('requests information and moves the application to info_requested', function () {
    $application = OnboardingApplication::factory()->create();

    app(RequestApplicationInformationAction::class)
        ->execute($application, userWithRole('sales_admin'), 'Need a clearer CIPC doc');

    expect($application->fresh()->status)->toBe(ApplicationStatus::InfoRequested);
});

it('records a CGIC outcome and is gated to finance', function () {
    $application = OnboardingApplication::factory()->credit()->create();

    app(RecordCgicOutcomeAction::class)
        ->execute($application, userWithRole('finance_admin'), CgicStatus::Approved, 'CG-123', 'approved by insurer');

    $application->refresh();
    expect($application->cgic_status)->toBe(CgicStatus::Approved)
        ->and($application->cgic_reference)->toBe('CG-123')
        ->and($application->cgic_decided_by)->not->toBeNull();

    expect(fn () => app(RecordCgicOutcomeAction::class)
        ->execute($application, userWithRole('sales_admin'), CgicStatus::Approved))
        ->toThrow(AuthorizationException::class);
});

it('reveals a principal id, returns the plaintext, and writes an audit entry', function () {
    $principal = OnboardingPrincipal::factory()->create(['id_number' => '9001015800086']);
    $actor = userWithRole('sales_admin');

    $revealed = app(RevealPrincipalIdAction::class)->execute($principal, $actor);

    expect($revealed)->toBe('9001015800086');
    expect(Activity::where('event', 'id_revealed')
        ->where('subject_id', $principal->id)
        ->where('causer_id', $actor->id)
        ->exists())->toBeTrue();
});

it('denies revealing an id without the process ability', function () {
    $principal = OnboardingPrincipal::factory()->create();

    expect(fn () => app(RevealPrincipalIdAction::class)->execute($principal, userWithRole('viewer')))
        ->toThrow(AuthorizationException::class);
});

it('returns the decrypted cgic payload to finance and audits access', function () {
    $application = OnboardingApplication::factory()->credit()->create([
        'cgic_payload' => json_encode(['banking' => ['bank' => 'Demo Bank']]),
    ]);
    $actor = userWithRole('finance_admin');

    $payload = app(ViewCgicPayloadAction::class)->execute($application, $actor);

    expect($payload)->toBe(['banking' => ['bank' => 'Demo Bank']]);
    expect(Activity::where('event', 'cgic_payload_accessed')
        ->where('subject_id', $application->id)
        ->exists())->toBeTrue();
});

it('denies cgic payload access without manage_company_credit', function () {
    $application = OnboardingApplication::factory()->credit()->create();

    expect(fn () => app(ViewCgicPayloadAction::class)->execute($application, userWithRole('sales_admin')))
        ->toThrow(AuthorizationException::class);
});

it('generates a short-lived signed url for a document and audits the access', function () {
    Storage::fake('r2');
    Storage::disk('r2')->buildTemporaryUrlsUsing(fn (string $path, $expiry): string => 'https://signed.test/'.$path);

    $document = OnboardingDocument::factory()->create(['disk' => 'r2', 'path' => 'onboarding/x/form.pdf']);

    $url = app(GenerateOnboardingDocumentUrlAction::class)->execute($document, userWithRole('sales_admin'));

    expect($url)->toBe('https://signed.test/onboarding/x/form.pdf');
    expect(Activity::where('event', 'document_accessed')->where('subject_id', $document->id)->exists())->toBeTrue();
});

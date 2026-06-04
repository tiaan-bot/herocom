<?php

declare(strict_types=1);

use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Onboarding\Models\OnboardingPrincipal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('encrypts the principal id_number at rest and decrypts on read', function () {
    $principal = OnboardingPrincipal::factory()->create(['id_number' => '9001015800086']);

    $raw = DB::table('onboarding_principals')->where('id', $principal->id)->value('id_number');

    expect($raw)->not->toBe('9001015800086')
        ->and(Crypt::decryptString($raw))->toBe('9001015800086')
        ->and($principal->fresh()->id_number)->toBe('9001015800086');
});

it('hides id_number from array/json serialization', function () {
    $principal = OnboardingPrincipal::factory()->create();

    expect($principal->toArray())->not->toHaveKey('id_number');
});

it('encrypts the cgic_payload at rest', function () {
    $payload = json_encode(['banking' => ['bank' => 'Secret Bank']]);
    $application = OnboardingApplication::factory()->create(['cgic_payload' => $payload]);

    $raw = DB::table('onboarding_applications')->where('id', $application->id)->value('cgic_payload');

    expect($raw)->not->toBe($payload)
        ->and(Crypt::decryptString($raw))->toBe($payload)
        ->and($application->toArray())->not->toHaveKey('cgic_payload');
});

it('never logs id_number to the activity log', function () {
    $principal = OnboardingPrincipal::factory()->create(['full_name' => 'Original']);

    $principal->update([
        'full_name' => 'Changed',
        'id_number' => '8001015009087',
    ]);

    $activity = Activity::where('subject_type', $principal->getMorphClass())
        ->where('subject_id', $principal->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();

    $changes = $activity->attribute_changes->toArray();

    // full_name is auditable; id_number must never appear in either attributes or old.
    expect($changes['attributes'] ?? [])->toHaveKey('full_name')
        ->and($changes['attributes'] ?? [])->not->toHaveKey('id_number')
        ->and($changes['old'] ?? [])->not->toHaveKey('id_number');
});

it('never logs cgic_payload to the activity log', function () {
    $application = OnboardingApplication::factory()->create();

    $application->update([
        'cgic_reference' => 'CGIC-123',
        'cgic_payload' => json_encode(['secret' => true]),
    ]);

    $activity = Activity::where('subject_type', $application->getMorphClass())
        ->where('subject_id', $application->id)
        ->latest('id')
        ->first();

    $changes = $activity->attribute_changes->toArray();

    expect($changes['attributes'] ?? [])->not->toHaveKey('cgic_payload')
        ->and($changes['old'] ?? [])->not->toHaveKey('cgic_payload');
});

<?php

declare(strict_types=1);

use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Ordering\Enums\CartStatus;
use App\Domain\Ordering\Models\Cart;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

$email = 'qa-reseller@herocom.co.za';
$companyName = 'QA Test Reseller (DELETE BEFORE GO-LIVE)';

it('creates an approved reseller user with the reseller role, ready to log in', function () use ($email) {
    $this->artisan('herocom:make-test-reseller', ['--password' => 'qa-password-123'])
        ->assertSuccessful();

    $user = User::where('email', $email)->sole();

    expect($user->company_id)->not->toBeNull()
        ->and($user->hasRole('reseller_owner'))->toBeTrue()
        ->and($user->password_set_at)->not->toBeNull()
        ->and($user->company->status)->toBe(CompanyStatus::Approved);

    // The credentials work immediately — no set-password link needed.
    expect(Auth::attempt(['email' => $email, 'password' => 'qa-password-123']))->toBeTrue();
});

it('is idempotent — re-running does not duplicate the user or company', function () use ($email, $companyName) {
    $this->artisan('herocom:make-test-reseller', ['--password' => 'qa-password-123'])->assertSuccessful();
    $this->artisan('herocom:make-test-reseller', ['--password' => 'qa-password-123'])->assertSuccessful();

    expect(User::where('email', $email)->count())->toBe(1)
        ->and(Company::where('legal_name', $companyName)->count())->toBe(1);
});

it('requires a password when creating', function () {
    $this->artisan('herocom:make-test-reseller')->assertFailed();

    expect(User::where('email', 'qa-reseller@herocom.co.za')->exists())->toBeFalse();
});

it('removes the test user, company and cart rows with --delete', function () use ($email, $companyName) {
    $this->artisan('herocom:make-test-reseller', ['--password' => 'qa-password-123'])->assertSuccessful();

    $user = User::where('email', $email)->sole();
    Cart::create([
        'user_id' => $user->id,
        'company_id' => $user->company_id,
        'status' => CartStatus::Open,
    ]);

    $this->artisan('herocom:make-test-reseller', ['--delete' => true])->assertSuccessful();

    expect(User::withTrashed()->where('email', $email)->exists())->toBeFalse()
        ->and(Company::withTrashed()->where('legal_name', $companyName)->exists())->toBeFalse()
        ->and(Cart::where('user_id', $user->id)->exists())->toBeFalse();
});

it('refuses to run in production without --force', function () use ($email) {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('herocom:make-test-reseller', ['--password' => 'qa-password-123'])
        ->assertFailed();

    expect(User::where('email', $email)->exists())->toBeFalse();
});

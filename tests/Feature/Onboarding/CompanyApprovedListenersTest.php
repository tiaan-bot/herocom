<?php

declare(strict_types=1);

use App\Domain\Onboarding\Actions\ApproveOnboardingApplicationAction;
use App\Domain\Onboarding\Events\CompanyApproved;
use App\Domain\Onboarding\Listeners\PushCompanyToZoho;
use App\Domain\Onboarding\Listeners\SendWelcomeEmail;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Shared\Zoho\Models\ZohoToken;
use App\Models\User;
use App\Notifications\OnboardingWelcomeNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function fakeZoho(): void
{
    config([
        'zoho.client_id' => 'cid',
        'zoho.client_secret' => 'secret',
        'zoho.organization_id' => 'org-1',
        'zoho.accounts_domain' => 'accounts.zoho.com',
        'zoho.api_domain' => 'www.zohoapis.com',
    ]);
    ZohoToken::query()->create([
        'refresh_token' => 'r',
        'access_token' => 'valid',
        'access_token_expires_at' => now()->addHour(),
    ]);
    Http::preventStrayRequests();
    Http::fake(['*/books/v3/contacts*' => Http::response(['contact' => ['contact_id' => 'z-cust-1']])]);
}

it('sends the welcome notification to the owner', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $event = new CompanyApproved(
        Company::factory()->create(),
        OnboardingApplication::factory()->create(),
        $owner,
    );

    (new SendWelcomeEmail)->handle($event);

    Notification::assertSentTo($owner, OnboardingWelcomeNotification::class);
});

it('skips the Zoho push when the company already has a zoho_customer_id', function () {
    $company = Company::factory()->create(['zoho_customer_id' => 'zoho-123']);
    $event = new CompanyApproved($company, OnboardingApplication::factory()->create(), User::factory()->create());

    app(PushCompanyToZoho::class)->handle($event);

    expect($company->fresh()->zoho_customer_id)->toBe('zoho-123');
});

it('wires CompanyApproved through to the welcome + Zoho listeners on approval', function () {
    $this->seed(RolePermissionSeeder::class);
    Notification::fake();
    fakeZoho();
    $application = OnboardingApplication::factory()->create();

    app(ApproveOnboardingApplicationAction::class)->execute($application, userWithRole('sales_admin'));

    $owner = User::where('email', $application->contact_email)->first();
    Notification::assertSentTo($owner, OnboardingWelcomeNotification::class);
    // PushCompanyToZoho ran (sync queue) and created the Zoho contact.
    expect($application->fresh()->company->zoho_customer_id)->toBe('z-cust-1');
});

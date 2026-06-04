<?php

declare(strict_types=1);

use App\Domain\Onboarding\Actions\ApproveOnboardingApplicationAction;
use App\Domain\Onboarding\Events\CompanyApproved;
use App\Domain\Onboarding\Listeners\PushCompanyToZoho;
use App\Domain\Onboarding\Listeners\SendWelcomeEmail;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Models\User;
use App\Notifications\OnboardingWelcomeNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

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

it('wires CompanyApproved through to the welcome listener on approval', function () {
    $this->seed(RolePermissionSeeder::class);
    Notification::fake();
    $application = OnboardingApplication::factory()->create();

    app(ApproveOnboardingApplicationAction::class)->execute($application, userWithRole('sales_admin'));

    $owner = User::where('email', $application->contact_email)->first();
    Notification::assertSentTo($owner, OnboardingWelcomeNotification::class);
});

<?php

namespace App\Providers;

use App\Domain\Onboarding\Events\CompanyApproved;
use App\Domain\Onboarding\Listeners\PushCompanyToZoho;
use App\Domain\Onboarding\Listeners\SendWelcomeEmail;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Onboarding\Policies\CompanyPolicy;
use App\Domain\Onboarding\Policies\OnboardingApplicationPolicy;
use App\Domain\Ordering\Events\OrderPlaced;
use App\Domain\Ordering\Listeners\PushOrderToZoho;
use App\Domain\Ordering\Listeners\SendOrderConfirmationEmail;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Domain models live under app/Domain/*, so policies are registered
     * explicitly rather than via auto-discovery.
     *
     * @var array<class-string, class-string>
     */
    private const POLICIES = [
        OnboardingApplication::class => OnboardingApplicationPolicy::class,
        Company::class => CompanyPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // super_admin bypasses all permission checks. Return null (not false) for
        // everyone else so normal ability/policy resolution still runs.
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole('super_admin') ? true : null;
        });

        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Event::listen(CompanyApproved::class, PushCompanyToZoho::class);
        Event::listen(CompanyApproved::class, SendWelcomeEmail::class);

        Event::listen(OrderPlaced::class, PushOrderToZoho::class);
        Event::listen(OrderPlaced::class, SendOrderConfirmationEmail::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Listeners;

use App\Domain\Onboarding\Events\CompanyApproved;
use App\Notifications\OnboardingWelcomeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class SendWelcomeEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300, 900, 3600];

    public function handle(CompanyApproved $event): void
    {
        $event->owner->notify(new OnboardingWelcomeNotification);
    }
}

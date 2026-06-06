<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Listeners;

use App\Domain\Onboarding\Actions\EnsureZohoCustomerAction;
use App\Domain\Onboarding\Events\CompanyApproved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class PushCompanyToZoho implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300, 900, 3600];

    public function __construct(
        private readonly EnsureZohoCustomerAction $ensureCustomer,
    ) {}

    public function handle(CompanyApproved $event): void
    {
        // Idempotent: returns early if already a Zoho customer; otherwise creates
        // the contact and persists zoho_customer_id (unique constraint guards retries).
        $this->ensureCustomer->execute($event->company);
    }
}

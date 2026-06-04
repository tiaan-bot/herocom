<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Listeners;

use App\Domain\Onboarding\Events\CompanyApproved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerInterface;

final class PushCompanyToZoho implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300, 900, 3600];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(CompanyApproved $event): void
    {
        // Idempotency anchor: once a company has a zoho_customer_id it has been pushed.
        if (filled($event->company->zoho_customer_id)) {
            return;
        }

        // TODO(zoho): replace with ZohoClient customer create once the Zoho OAuth
        // foundation is wired (Services/Zoho). On success, set & persist
        // $event->company->zoho_customer_id (unique constraint keeps retries safe).
        $this->logger->info('Onboarding: queued Zoho customer push (stub).', [
            'company_uuid' => $event->company->uuid,
            'legal_name' => $event->company->legal_name,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Jobs;

use App\Domain\Catalog\Actions\SyncProductsFromZoho;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class SyncZohoProducts implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * Large catch-ups (a long backlog, or a full reconcile) page through many
     * items with a detail call each, so allow well beyond the default 60s. Kept
     * under the 30-minute schedule interval; the queue worker's --timeout must be
     * at least this high in production (Horizon `timeout`). A run that still hits
     * the limit is safe: the cursor only advances after a complete pagination, so
     * a timed-out run re-runs from the same point rather than skipping items.
     */
    public int $timeout = 1500;

    /** A timeout counts as a failed attempt so it is retried (and surfaced). */
    public bool $failOnTimeout = true;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300, 900, 3600];

    public function __construct(
        public readonly bool $full = false,
    ) {}

    public function handle(SyncProductsFromZoho $action): void
    {
        $action->execute($this->full);
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Zoho product sync failed.', [
            'full' => $this->full,
            'exception' => $exception->getMessage(),
        ]);
    }
}

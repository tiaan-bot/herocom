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

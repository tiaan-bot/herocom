<?php

declare(strict_types=1);

namespace App\Console\Commands\Zoho;

use App\Domain\Catalog\Actions\SyncProductsFromZoho;
use App\Domain\Shared\Zoho\Exceptions\ZohoException;
use Illuminate\Console\Command;

class SyncProductsCommand extends Command
{
    protected $signature = 'zoho:sync-products {--full : Walk all pages and deactivate items no longer in Zoho}';

    protected $description = 'Sync products from Zoho Books into the local catalog (one-way)';

    public function handle(SyncProductsFromZoho $action): int
    {
        $full = (bool) $this->option('full');
        $this->info($full ? 'Running a full Zoho product sync…' : 'Running an incremental Zoho product sync…');

        try {
            $result = $action->execute($full);
        } catch (ZohoException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced {$result->synced} item(s); deactivated {$result->deactivated}.");

        return self::SUCCESS;
    }
}

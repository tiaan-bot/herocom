<?php

declare(strict_types=1);

namespace App\Console\Commands\Zoho;

use App\Domain\Billing\Actions\SyncInvoicesFromZoho;
use App\Domain\Shared\Zoho\Exceptions\ZohoException;
use Illuminate\Console\Command;

class SyncInvoicesCommand extends Command
{
    protected $signature = 'zoho:sync-invoices {--full : Walk all pages instead of only those modified since the last sync}';

    protected $description = 'Sync invoices from Zoho Books into the local mirror (one-way)';

    public function handle(SyncInvoicesFromZoho $action): int
    {
        $full = (bool) $this->option('full');
        $this->info($full ? 'Running a full Zoho invoice sync…' : 'Running an incremental Zoho invoice sync…');

        try {
            $result = $action->execute($full);
        } catch (ZohoException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced {$result->synced} invoice(s); skipped {$result->skipped} (no portal company).");

        return self::SUCCESS;
    }
}

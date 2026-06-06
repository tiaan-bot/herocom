<?php

declare(strict_types=1);

namespace App\Console\Commands\Zoho;

use App\Domain\Shared\Zoho\Exceptions\ZohoException;
use App\Domain\Shared\Zoho\ZohoClient;
use Illuminate\Console\Command;

class PingZohoCommand extends Command
{
    protected $signature = 'zoho:ping';

    protected $description = 'Prove Zoho connectivity: fetch the organization and a page of items';

    public function handle(ZohoClient $client): int
    {
        try {
            $org = $client->getOrganization();
            $this->info('Connected to Zoho Books.');
            $this->line('Organization: '.($org['name'] ?? '(unknown)').' [#'.($org['organization_id'] ?? '?').']');

            $items = $client->listItems(1);
            $this->line('Fetched '.count($items).' item(s):');
            foreach (array_slice($items, 0, 10) as $item) {
                $this->line('  • '.($item['name'] ?? '(unnamed)').' — '.($item['rate'] ?? '?'));
            }
        } catch (ZohoException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

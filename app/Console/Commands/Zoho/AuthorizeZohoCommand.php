<?php

declare(strict_types=1);

namespace App\Console\Commands\Zoho;

use App\Domain\Shared\Zoho\Exceptions\ZohoException;
use App\Domain\Shared\Zoho\ZohoClient;
use Illuminate\Console\Command;

class AuthorizeZohoCommand extends Command
{
    protected $signature = 'zoho:authorize {grantToken : The self-client grant token from api-console.zoho.com}';

    protected $description = 'Exchange a Zoho self-client grant token for a stored, encrypted refresh token';

    public function handle(ZohoClient $client): int
    {
        try {
            $token = $client->authorize((string) $this->argument('grantToken'));
        } catch (ZohoException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Never print the token itself.
        $this->info('Zoho authorized. Refresh token stored (encrypted).');
        $this->line('Scopes: '.implode(', ', $token->scopes ?? []));
        $this->line('Next: run `php artisan zoho:ping` to verify connectivity.');

        return self::SUCCESS;
    }
}

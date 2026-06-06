<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Zoho data-centre domains
|--------------------------------------------------------------------------
|
| Zoho is region-partitioned: the accounts (OAuth token) domain and the API
| domain both differ per data centre. Never hard-code one — derive both from
| ZOHO_REGION so an EU/IN/AU/JP org works without code changes.
|
*/
$region = env('ZOHO_REGION', 'com');

$domains = [
    'com' => ['accounts' => 'accounts.zoho.com', 'api' => 'www.zohoapis.com'],
    'eu' => ['accounts' => 'accounts.zoho.eu', 'api' => 'www.zohoapis.eu'],
    'in' => ['accounts' => 'accounts.zoho.in', 'api' => 'www.zohoapis.in'],
    'com.au' => ['accounts' => 'accounts.zoho.com.au', 'api' => 'www.zohoapis.com.au'],
    'jp' => ['accounts' => 'accounts.zoho.jp', 'api' => 'www.zohoapis.jp'],
    'ca' => ['accounts' => 'accounts.zohocloud.ca', 'api' => 'www.zohoapis.ca'],
];

$selected = $domains[$region] ?? $domains['com'];

return [

    'client_id' => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'organization_id' => env('ZOHO_ORGANIZATION_ID'),

    'region' => $region,
    'accounts_domain' => $selected['accounts'],
    'api_domain' => $selected['api'],

    /*
    | Phase 1 scope set — requested once at grant-token generation so we don't
    | re-authorize per domain later.
    */
    'scopes' => [
        'ZohoBooks.items.READ',
        'ZohoBooks.contacts.CREATE',
        'ZohoBooks.contacts.READ',
        'ZohoBooks.salesorders.CREATE',
        'ZohoBooks.salesorders.READ',
        'ZohoBooks.invoices.READ',
        'ZohoBooks.settings.READ',
    ],

    'timeout' => (int) env('ZOHO_TIMEOUT', 30),

    'retry' => [
        'max_attempts' => (int) env('ZOHO_RETRY_ATTEMPTS', 4),
        'base_backoff_ms' => (int) env('ZOHO_RETRY_BACKOFF_MS', 1000),
    ],

    // Seconds shaved off the reported access-token lifetime as a safety margin.
    'token_expiry_buffer' => (int) env('ZOHO_TOKEN_EXPIRY_BUFFER', 60),

];

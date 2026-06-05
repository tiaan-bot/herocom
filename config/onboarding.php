<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Onboarding document storage
    |--------------------------------------------------------------------------
    |
    | Onboarding documents are PII and live on a PRIVATE disk, served only via
    | short-lived signed URLs. Production uses Cloudflare R2 ("r2"). In local
    | dev, set ONBOARDING_DOCUMENT_DISK=local until R2 credentials are wired —
    | the "local" disk has serve=true, so temporaryUrl() works there too.
    |
    */

    'documents' => [
        'disk' => env('ONBOARDING_DOCUMENT_DISK', 'r2'),
        'url_ttl_minutes' => (int) env('ONBOARDING_DOCUMENT_URL_TTL', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Terms & Conditions
    |--------------------------------------------------------------------------
    |
    | The currently-hosted T&Cs version captured against each application at
    | submission, plus a link the public form can point at. A proper versioned
    | document store / re-acceptance workflow is deferred (see CLAUDE.md).
    |
    */

    'terms' => [
        'version' => env('ONBOARDING_TERMS_VERSION', '2026-01'),
        'url' => env('ONBOARDING_TERMS_URL', '/terms-of-sale.pdf'),
    ],

];

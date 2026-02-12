<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed Sender Domains
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of domains allowed for outbound broadcast sender
    | addresses. Example: "test.com,marketing.test.com".
    |
    */

    'allowed_domains' => collect(explode(',', (string) env('ALLOWED_DOMAIN', '')))
        ->map(static fn (string $domain): string => trim($domain))
        ->filter(static fn (string $domain): bool => $domain !== '')
        ->values()
        ->all(),

];

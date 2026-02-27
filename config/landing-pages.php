<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Landing Page Domains
    |--------------------------------------------------------------------------
    |
    | Exact domains allowed to serve landing pages at root path.
    | Example: event.example.com,marketing.example.com
    |
    */
    'domains' => array_values(array_filter(array_map(
        static fn (string $domain): string => strtolower(trim($domain)),
        explode(',', (string) env('LANDING_PAGE_DOMAINS', 'event.example.com,marketing.example.com')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Landing Page Wildcard Root
    |--------------------------------------------------------------------------
    |
    | Optional wildcard root domain for subdomain landing pages.
    | Example: example.com will match {subdomain}.example.com.
    |
    */
    'wildcard_root' => strtolower((string) env('LANDING_PAGE_WILDCARD_ROOT', 'example.com')),
];

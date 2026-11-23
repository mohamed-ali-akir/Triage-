<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication for the support staff
    |--------------------------------------------------------------------------
    |
    | When enabled, technicians and administrators must turn on two-factor
    | authentication before they can use the application or the mobile API.
    |
    */

    'require_two_factor_for_staff' => (bool) env('REQUIRE_2FA_FOR_STAFF', false),

    /*
    |--------------------------------------------------------------------------
    | Reverse proxy
    |--------------------------------------------------------------------------
    |
    | Behind a reverse proxy (nginx, load balancer), every request seems to come
    | from the proxy. List its address here, comma separated, so that the real
    | address of the visitor is used for rate limits and for the audit journal.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | One account cannot flood the queue, the AI budget or the mailboxes.
    |
    */

    'limits' => [
        'tickets_per_hour' => (int) env('LIMIT_TICKETS_PER_HOUR', 20),
        'messages_per_minute' => (int) env('LIMIT_MESSAGES_PER_MINUTE', 10),
        'api_requests_per_minute' => (int) env('LIMIT_API_REQUESTS_PER_MINUTE', 120),
    ],

];

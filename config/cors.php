<?php

/*
 * Lets websites on any address call the API straight from a visitor's browser.
 * Keys are sent in the Authorization header (no cookies), so allowing every
 * origin opens nothing extra: a request still needs a valid key.
 */
return [

    'paths' => ['v1/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Accept', 'Content-Type', 'If-None-Match'],

    // Let browser code read how many requests are left and when to retry.
    'exposed_headers' => ['ETag', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After'],

    // Browsers may reuse the preflight answer for a day.
    'max_age' => 86400,

    'supports_credentials' => false,

];

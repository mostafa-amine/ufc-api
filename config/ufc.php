<?php

return [
    'base_url' => env('UFC_BASE_URL', 'http://ufcstats.com'),

    // Identify the scraper honestly.
    'user_agent' => env('UFC_USER_AGENT', 'ufc-api (open-source; +https://github.com/) contact: cloudbriere@gmail.com'),

    'timeout' => (int) env('UFC_TIMEOUT', 30),

    // Politeness: jittered delay (ms) between page fetches.
    'delay_min_ms' => (int) env('UFC_DELAY_MIN_MS', 150),
    'delay_max_ms' => (int) env('UFC_DELAY_MAX_MS', 450),

    'retries' => (int) env('UFC_RETRIES', 3),

    // Cache key under which the solved anti-bot clearance cookie is stored.
    'clearance_cache_key' => 'ufc:clearance-cookies',
];

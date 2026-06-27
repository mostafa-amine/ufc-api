<?php

namespace App\Scraping;

/**
 * Fetches a ufcstats page and returns its HTML. Abstracts the network so the
 * scrape orchestrator can be exercised with fixtures (no live requests) in tests.
 */
interface PageFetcher
{
    /** @throws \RuntimeException when the page cannot be retrieved. */
    public function fetch(string $url): string;
}

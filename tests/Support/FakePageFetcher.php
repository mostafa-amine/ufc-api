<?php

namespace Tests\Support;

use App\Scraping\PageFetcher;
use RuntimeException;

/**
 * Serves saved ufcstats fixtures by URL so the scrape pipeline can be tested
 * without network. Unknown fight/fighter pages throw (mirroring a fetch failure),
 * exercising the orchestrator's per-item error tolerance.
 */
class FakePageFetcher implements PageFetcher
{
    /** @var array<int, string> */
    public array $requested = [];

    public function __construct(private string $fixtureDir) {}

    public function fetch(string $url): string
    {
        $this->requested[] = $url;

        if (str_contains($url, '/statistics/events/completed')) {
            return $this->file('events_completed.html');
        }
        if (str_contains($url, '/statistics/events/upcoming')) {
            return '<html><body><table></table></body></html>';
        }
        if (preg_match('#/event-details/[a-f0-9]+#', $url)) {
            return $this->file('event_detail.html');
        }
        if (preg_match('#/fight-details/([a-f0-9]+)#', $url, $m)) {
            return $this->oneOf(["fight_{$m[1]}.html", "fight_decision2_{$m[1]}.html"], $url);
        }
        if (preg_match('#/fighter-details/([a-f0-9]+)#', $url, $m)) {
            return $this->oneOf(["fighter_{$m[1]}.html"], $url);
        }

        throw new RuntimeException("No fixture mapping for: {$url}");
    }

    private function oneOf(array $candidates, string $url): string
    {
        foreach ($candidates as $name) {
            $path = "{$this->fixtureDir}/{$name}";
            if (is_file($path)) {
                return file_get_contents($path);
            }
        }

        throw new RuntimeException("No fixture for: {$url}");
    }

    private function file(string $name): string
    {
        return file_get_contents("{$this->fixtureDir}/{$name}");
    }
}

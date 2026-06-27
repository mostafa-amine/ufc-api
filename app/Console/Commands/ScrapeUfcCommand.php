<?php

namespace App\Console\Commands;

use App\Scraping\ScrapeUfc;
use Illuminate\Console\Command;

class ScrapeUfcCommand extends Command
{
    protected $signature = 'ufc:scrape
        {--all : Full backfill of every known event}
        {--event= : Scrape a single event by its ufcstats id}
        {--fighter= : Scrape a single fighter by its ufcstats id}
        {--refresh-fighters : Re-fetch fighter profiles even if recently scraped}';

    protected $description = 'Scrape UFC events, fights and fighters from ufcstats.com';

    public function handle(ScrapeUfc $scraper): int
    {
        if ($eventId = $this->option('event')) {
            $this->info("Scraping event {$eventId}...");
            $event = $scraper->scrapeEvent($eventId, (bool) $this->option('refresh-fighters'));
            $this->info($event ? "Done: {$event->name}" : 'Event scrape failed.');

            return $event ? self::SUCCESS : self::FAILURE;
        }

        if ($fighterId = $this->option('fighter')) {
            $this->info("Scraping fighter {$fighterId}...");
            $fighter = $scraper->scrapeFighter($fighterId);
            $this->info($fighter ? "Done: {$fighter->name}" : 'Fighter scrape failed.');

            return $fighter ? self::SUCCESS : self::FAILURE;
        }

        $run = $this->option('all')
            ? $scraper->full()
            : $scraper->incremental();

        $this->table(
            ['Type', 'Status', 'Events', 'Fights', 'Fighters', 'Errors'],
            [[
                $run->type, $run->status, $run->events_scraped,
                $run->fights_scraped, $run->fighters_scraped, count($run->errors ?? []),
            ]],
        );

        return $run->status === 'success' ? self::SUCCESS : self::FAILURE;
    }
}

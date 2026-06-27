<?php

namespace App\Scraping;

use App\Models\Event;
use App\Models\Fighter;
use App\Models\ScrapeRun;
use App\Scraping\Actions\UpsertEvent;
use App\Scraping\Actions\UpsertFight;
use App\Scraping\Actions\UpsertFighter;
use App\Scraping\Parsers\EventDetailParser;
use App\Scraping\Parsers\EventListParser;
use App\Scraping\Parsers\FightDetailParser;
use App\Scraping\Parsers\FighterParser;
use Carbon\CarbonImmutable;
use Closure;
use Throwable;

/**
 * Orchestrates the scrape pipeline: discover events, scrape event bouts + fight
 * stats, refresh fighters. All network goes through a PageFetcher so the flow is
 * testable with fixtures. Per-item failures are collected, never fatal.
 */
class ScrapeUfc
{
    /** @var array<int, array{stage:string,id:string,error:string}> */
    private array $errors = [];

    private int $eventsScraped = 0;

    private int $fightsScraped = 0;

    private int $fightersScraped = 0;

    public function __construct(
        private PageFetcher $fetcher,
        private EventListParser $eventList,
        private EventDetailParser $eventDetail,
        private FightDetailParser $fightDetail,
        private FighterParser $fighterParser,
        private UpsertEvent $upsertEvent,
        private UpsertFight $upsertFight,
        private UpsertFighter $upsertFighter,
    ) {}

    private function base(): string
    {
        return rtrim(config('ufc.base_url'), '/');
    }

    /** Discover events from the completed + upcoming listings. */
    public function syncEventList(): int
    {
        $count = 0;
        foreach (['completed' => 'completed', 'upcoming' => 'upcoming'] as $path => $status) {
            try {
                $html = $this->fetcher->fetch($this->base()."/statistics/events/{$path}?page=all");
                foreach ($this->eventList->parse($html) as $event) {
                    $this->upsertEvent->upsert($event['ufcstats_id'], [
                        'name' => $event['name'],
                        'date' => $event['date'],
                        'location_raw' => $event['location_raw'],
                        'status' => $status,
                        'url' => $event['url'],
                    ]);
                    $count++;
                }
            } catch (Throwable $e) {
                $this->errors[] = ['stage' => 'event-list', 'id' => $path, 'error' => $e->getMessage()];
            }
        }

        return $count;
    }

    /**
     * Scrape one event: meta, bouts, fight stats, then referenced fighters.
     */
    public function scrapeEvent(string $eventId, bool $refreshFighters = false): ?Event
    {
        try {
            $html = $this->fetcher->fetch($this->base()."/event-details/{$eventId}");
        } catch (Throwable $e) {
            $this->errors[] = ['stage' => 'event', 'id' => $eventId, 'error' => $e->getMessage()];

            return null;
        }

        $data = $this->eventDetail->parse($html);

        $event = $this->upsertEvent->upsert($eventId, [
            'name' => $data['event']['name'] ?: null,
            'date' => $data['event']['date'],
            'location_raw' => $data['event']['location_raw'],
            'url' => $this->base()."/event-details/{$eventId}",
        ]);

        $fighterIds = [];
        $anyResult = false;

        foreach ($data['bouts'] as $bout) {
            $detail = $this->fetchFightDetail($bout['ufcstats_id']);

            $this->upsertFight->upsert($event, $bout, $detail);
            $this->fightsScraped++;

            foreach ($bout['fighters'] as $f) {
                if ($f['ufcstats_id']) {
                    $fighterIds[$f['ufcstats_id']] = $f['name'];
                }
            }
            if ($detail) {
                foreach ($detail['persons'] as $p) {
                    if ($p['fighter_ufcstats_id']) {
                        $fighterIds[$p['fighter_ufcstats_id']] = $p['name'];
                    }
                }
                if (($detail['outcome'] ?? 'pending') !== 'pending') {
                    $anyResult = true;
                }
            }
        }

        foreach ($fighterIds as $id => $name) {
            $this->refreshFighter($id, $refreshFighters);
        }

        $event->update([
            'status' => $anyResult ? 'completed' : ($event->status ?? 'upcoming'),
            'last_scraped_at' => now(),
        ]);
        $this->eventsScraped++;

        return $event->refresh();
    }

    private function fetchFightDetail(string $fightId): ?array
    {
        try {
            $html = $this->fetcher->fetch($this->base()."/fight-details/{$fightId}");

            return $this->fightDetail->parse($html);
        } catch (Throwable $e) {
            $this->errors[] = ['stage' => 'fight', 'id' => $fightId, 'error' => $e->getMessage()];

            return null;
        }
    }

    /** Fetch + upsert a fighter profile (skips if recently scraped unless forced). */
    public function scrapeFighter(string $fighterId): ?Fighter
    {
        try {
            $html = $this->fetcher->fetch($this->base()."/fighter-details/{$fighterId}");
            $data = $this->fighterParser->parse($html);
            $fighter = $this->upsertFighter->fromProfile($fighterId, $data, $this->base()."/fighter-details/{$fighterId}");
            $this->fightersScraped++;

            return $fighter;
        } catch (Throwable $e) {
            $this->errors[] = ['stage' => 'fighter', 'id' => $fighterId, 'error' => $e->getMessage()];

            return null;
        }
    }

    private function refreshFighter(string $id, bool $force): void
    {
        $existing = Fighter::where('ufcstats_id', $id)->first();
        $fresh = $existing
            && $existing->last_scraped_at
            && $existing->last_scraped_at->gt(CarbonImmutable::now()->subDays(7));

        if (! $force && $fresh) {
            return;
        }

        // Ensure a shell exists even if the profile fetch fails.
        $this->upsertFighter->shell($id);
        $this->scrapeFighter($id);
    }

    /**
     * Incremental run: sync the event list, then scrape events that are new
     * (never scraped) or still upcoming.
     */
    public function incremental(): ScrapeRun
    {
        return $this->track('incremental', function () {
            $this->syncEventList();

            Event::query()
                ->where(fn ($q) => $q->whereNull('last_scraped_at')->orWhere('status', 'upcoming'))
                ->orderByDesc('date')
                ->get()
                ->each(fn (Event $e) => $this->scrapeEvent($e->ufcstats_id));
        });
    }

    /** Full backfill: every known event. */
    public function full(): ScrapeRun
    {
        return $this->track('full', function () {
            $this->syncEventList();
            Event::query()->orderBy('date')->get()
                ->each(fn (Event $e) => $this->scrapeEvent($e->ufcstats_id));
        });
    }

    private function track(string $type, Closure $work): ScrapeRun
    {
        $run = ScrapeRun::create(['type' => $type, 'status' => 'running', 'started_at' => now()]);
        $this->errors = [];
        $this->eventsScraped = $this->fightsScraped = $this->fightersScraped = 0;

        try {
            $work();
            $status = 'success';
        } catch (Throwable $e) {
            $this->errors[] = ['stage' => 'run', 'id' => $type, 'error' => $e->getMessage()];
            $status = 'failed';
        }

        $run->update([
            'status' => $status,
            'finished_at' => now(),
            'events_scraped' => $this->eventsScraped,
            'fights_scraped' => $this->fightsScraped,
            'fighters_scraped' => $this->fightersScraped,
            'errors' => $this->errors ?: null,
        ]);

        return $run->refresh();
    }
}

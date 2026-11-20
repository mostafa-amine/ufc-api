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
use Illuminate\Support\Facades\Log;
use RuntimeException;
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
                $events = $this->eventList->parse($html);

                // The completed list always holds hundreds of events: an empty one means
                // ufcstats sent a page we can't read (a block page or a new layout).
                if ($path === 'completed' && $events === []) {
                    throw new RuntimeException('The completed events list had no events we could read.');
                }

                foreach ($events as $event) {
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

    /** How far back an event keeps being re-checked while some of its fights have no result. */
    private const RESULT_WINDOW_DAYS = 30;

    /**
     * Incremental run: sync the event list, then scrape events that are new
     * (never scraped), still upcoming, or recent but missing fight results.
     *
     * The last case matters because an event is usually first scraped before
     * fight night, and the list sync then flips it to "completed" on its own:
     * without it, the results would never be fetched.
     */
    public function incremental(): ScrapeRun
    {
        return $this->track('incremental', function () {
            $this->syncEventList();

            Event::query()
                ->where(fn ($q) => $q->whereNull('last_scraped_at')
                    ->orWhere('status', 'upcoming')
                    ->orWhere(fn ($q) => $q
                        ->where('date', '>=', CarbonImmutable::today()->subDays(self::RESULT_WINDOW_DAYS))
                        ->whereHas('fights', fn ($f) => $f->where('outcome', 'pending'))))
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

    /** Scrape the N most recent COMPLETED events (the ones that actually have stats). */
    public function latest(int $count): ScrapeRun
    {
        return $this->track('latest', function () use ($count) {
            $this->syncEventList();
            Event::query()
                ->where('status', 'completed')
                ->whereNotNull('date')
                ->orderByDesc('date')
                ->limit(max(1, $count))
                ->get()
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
        } catch (Throwable $e) {
            $this->errors[] = ['stage' => 'run', 'id' => $type, 'error' => $e->getMessage()];
        }

        $status = $this->outcome();
        $this->report($type, $status);

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

    /**
     * success: nothing went wrong. partial: some pages failed, the rest saved.
     * failed: the run crashed, the completed events list could not be read (so
     * nothing new can be found), or every page it tried failed.
     */
    private function outcome(): string
    {
        if ($this->errors === []) {
            return 'success';
        }

        $stages = array_column($this->errors, 'stage', 'id');
        $listFailed = ($stages['completed'] ?? null) === 'event-list';
        $crashed = in_array('run', array_column($this->errors, 'stage'), true);
        $savedNothing = $this->eventsScraped + $this->fightsScraped + $this->fightersScraped === 0;

        return $crashed || $listFailed || $savedNothing ? 'failed' : 'partial';
    }

    private function report(string $type, string $status): void
    {
        if ($status === 'success') {
            return;
        }

        $context = [
            'type' => $type,
            'error_count' => count($this->errors),
            'errors' => array_slice($this->errors, 0, 20),
            'events' => $this->eventsScraped,
            'fights' => $this->fightsScraped,
            'fighters' => $this->fightersScraped,
        ];

        $status === 'failed'
            ? Log::error('[ufc:scrape] Run failed', $context)
            : Log::warning('[ufc:scrape] Run finished with errors', $context);
    }
}

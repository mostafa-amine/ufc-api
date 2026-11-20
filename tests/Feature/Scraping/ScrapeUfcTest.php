<?php

use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\RoundStat;
use App\Models\Scorecard;
use App\Models\ScrapeRun;
use App\Scraping\PageFetcher;
use App\Scraping\ScrapeUfc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakePageFetcher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(PageFetcher::class, new FakePageFetcher(base_path('tests/fixtures')));
});

it('scrapes an event into a full relational graph', function () {
    $event = app(ScrapeUfc::class)->scrapeEvent('31e1ea6fe6b682f8', refreshFighters: true);

    expect($event->name)->toBe('UFC Fight Night: Fiziev vs. Torres')
        ->and(Fight::count())->toBe(13); // all bouts as shells, even those without a fight fixture

    $decision = Fight::where('ufcstats_id', 'd83294b031502177')->first();
    expect($decision->method)->toBe('Decision - Unanimous')
        ->and($decision->redFighter->name)->toBe('Ikram Aliskerov')
        ->and($decision->blueFighter->name)->toBe('Brunno Ferreira')
        ->and($decision->winner->name)->toBe('Ikram Aliskerov')
        ->and($decision->outcome)->toBe('win');

    // Round stats: totals (round 0) + 3 rounds, for each of the 2 fighters.
    expect($decision->roundStats()->count())->toBe(8);
    $redTotal = $decision->roundStats()
        ->where('fighter_id', $decision->red_fighter_id)->where('round', 0)->first();
    expect($redTotal->sig_str_landed)->toBe(47)
        ->and($redTotal->control_time_sec)->toBe(533)
        ->and($redTotal->head_landed)->toBe(32);

    // Scorecards mapped to the winner corner (red won; "27 - 30" => red 30, blue 27).
    $cards = $decision->scorecards;
    expect($cards)->toHaveCount(3)
        ->and($cards->first()->red_score)->toBe(30)
        ->and($cards->first()->blue_score)->toBe(27);

    // A finish: KO with the blue corner winning, no scorecards.
    $ko = Fight::where('ufcstats_id', '809814f03ff3140c')->first();
    expect($ko->method)->toBe('KO/TKO')
        ->and($ko->winner->name)->toBe('Matheus Camilo')
        ->and($ko->scorecards()->count())->toBe(0);

    // Fighters referenced get at least a shell; those with a fixture get a full profile.
    $fiziev = Fighter::where('ufcstats_id', 'c814b4c899793af6')->first();
    expect($fiziev->height_in)->toBe(68)
        ->and($fiziev->stance)->toBe('Switch');
});

it('is idempotent across repeated scrapes', function () {
    $scraper = app(ScrapeUfc::class);
    $scraper->scrapeEvent('31e1ea6fe6b682f8', refreshFighters: true);

    $before = [
        'events' => Event::count(),
        'fights' => Fight::count(),
        'stats' => RoundStat::count(),
        'cards' => Scorecard::count(),
        'fighters' => Fighter::count(),
    ];

    $scraper->scrapeEvent('31e1ea6fe6b682f8', refreshFighters: true);

    expect([
        'events' => Event::count(),
        'fights' => Fight::count(),
        'stats' => RoundStat::count(),
        'cards' => Scorecard::count(),
        'fighters' => Fighter::count(),
    ])->toBe($before);
});

it('discovers events from the listing', function () {
    $count = app(ScrapeUfc::class)->syncEventList();

    expect($count)->toBeGreaterThan(700)
        ->and(Event::where('ufcstats_id', '31e1ea6fe6b682f8')->exists())->toBeTrue();
});

it('re-checks a recent event scraped before fight night until its results are in', function () {
    $fake = new FakePageFetcher(base_path('tests/fixtures'));
    $this->app->instance(PageFetcher::class, $fake);

    // An id outside the fixture list, so the list sync leaves its date alone.
    // Scraped the day before the card, so its fights were saved with no result yet.
    $event = Event::create([
        'ufcstats_id' => 'a0a0a0a0a0a0a0a0', 'name' => 'x', 'date' => now()->subDays(2)->toDateString(),
        // The list sync has already flipped it to completed, as it does after fight night.
        'status' => 'completed', 'last_scraped_at' => now()->subDays(3),
    ]);
    $event->fights()->create(['ufcstats_id' => 'd83294b031502177', 'outcome' => 'pending']);

    app(ScrapeUfc::class)->incremental();

    expect(collect($fake->requested)->filter(fn ($u) => str_contains($u, 'event-details/a0a0a0a0a0a0a0a0')))->not->toBeEmpty()
        ->and(Fight::where('ufcstats_id', 'd83294b031502177')->value('method'))->toBe('Decision - Unanimous');
});

it('leaves an older event with a missing result to the full backfill', function () {
    $fake = new FakePageFetcher(base_path('tests/fixtures'));
    $this->app->instance(PageFetcher::class, $fake);

    $event = Event::create([
        'ufcstats_id' => 'a0a0a0a0a0a0a0a0', 'name' => 'x', 'date' => now()->subDays(60)->toDateString(),
        'status' => 'completed', 'last_scraped_at' => now()->subDays(59),
    ]);
    $event->fights()->create(['ufcstats_id' => 'd83294b031502177', 'outcome' => 'pending']);

    app(ScrapeUfc::class)->incremental();

    expect(collect($fake->requested)->filter(fn ($u) => str_contains($u, 'event-details/a0a0a0a0a0a0a0a0')))->toBeEmpty();
});

it('fails the run when ufcstats sends a page we cannot read instead of the events list', function () {
    // What ufcstats serves when its browser check is not cleared.
    $this->app->instance(PageFetcher::class, new class implements PageFetcher
    {
        public function fetch(string $url): string
        {
            return '<html><body><p>Checking your browser…</p></body></html>';
        }
    });
    Log::spy();

    $this->artisan('ufc:scrape')->assertFailed();

    expect(ScrapeRun::latest('id')->value('status'))->toBe('failed');
    Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'Run failed'))->once();
    $this->getJson('/v1/health')->assertJsonPath('data.last_scrape.status', 'failed');
});

it('marks the run partial when some pages fail but the rest are saved', function () {
    $this->app->instance(PageFetcher::class, new FakePageFetcher(base_path('tests/fixtures')));
    Log::spy();

    // The fixture event lists bouts whose fight pages are missing, so those fetches fail.
    $run = app(ScrapeUfc::class)->latest(1);

    expect($run->status)->toBe('partial')
        ->and($run->events_scraped)->toBe(1)
        ->and($run->errors)->not->toBeEmpty();
    Log::shouldHaveReceived('warning')->once();
});

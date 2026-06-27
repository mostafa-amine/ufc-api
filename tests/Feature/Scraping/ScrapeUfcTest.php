<?php

use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\RoundStat;
use App\Models\Scorecard;
use App\Scraping\PageFetcher;
use App\Scraping\ScrapeUfc;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

<?php

use App\Models\Event;
use App\Models\Fight;
use App\Models\RoundStat;
use App\Models\Scorecard;
use App\Scraping\PageFetcher;
use App\Scraping\ScrapeUfc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePageFetcher;

uses(RefreshDatabase::class);

/** A finished bout with stats for both corners (totals + each round) and three cards. */
function landingDecision(Event $event, int $boutOrder, string $method, int $rounds = 3): Fight
{
    $fight = Fight::factory()->for($event)->create([
        'bout_order' => $boutOrder,
        'method' => $method,
        'scheduled_rounds' => $rounds,
        'time_format_raw' => $rounds.' Rnd ('.implode('-', array_fill(0, $rounds, 5)).')',
        'end_round' => $rounds,
    ]);

    foreach ([$fight->red_fighter_id, $fight->blue_fighter_id] as $fighterId) {
        foreach (range(0, $rounds) as $round) {
            RoundStat::factory()->create(['fight_id' => $fight->id, 'fighter_id' => $fighterId, 'round' => $round]);
        }
    }
    Scorecard::factory()->count(3)->create(['fight_id' => $fight->id]);

    return $fight;
}

/** The fight the page features, read from its closing "one request" example. */
function featuredFightId(string $html): ?string
{
    return preg_match_all('#/v1/fights/([a-f0-9]{16})#', $html, $m) === 1 ? $m[1][0] : null;
}

describe('which fight the landing page shows', function () {
    it('shows the decision highest on the card of the latest event', function () {
        landingDecision(Event::factory()->create(['date' => '2026-09-01']), 1, 'Decision - Unanimous');

        $latest = Event::factory()->create(['date' => '2026-09-20']);
        Fight::factory()->for($latest)->create(['bout_order' => 1, 'method' => 'KO/TKO']);
        landingDecision($latest, 8, 'Decision - Unanimous');
        $two = landingDecision($latest, 2, 'Decision - Unanimous');
        landingDecision($latest, 5, 'Decision - Unanimous');

        $html = $this->get('/')->assertOk()->getContent();

        expect(featuredFightId($html))->toBe($two->ufcstats_id);
    });

    it('moves to the newer decision once a newer event is scraped', function () {
        $first = landingDecision(Event::factory()->create(['date' => '2026-09-01']), 3, 'Decision - Unanimous');
        expect(featuredFightId($this->get('/')->getContent()))->toBe($first->ufcstats_id);

        $later = landingDecision(Event::factory()->create(['date' => '2026-09-08']), 4, 'Decision - Unanimous');

        expect(featuredFightId($this->get('/')->getContent()))->toBe($later->ufcstats_id);
    });

    it('skips a newer event whose bouts all ended before the judges', function () {
        $judged = landingDecision(Event::factory()->create(['date' => '2026-09-01']), 3, 'Decision - Unanimous');
        $finishes = Event::factory()->create(['date' => '2026-09-08']);
        Fight::factory()->for($finishes)->create(['bout_order' => 1, 'method' => 'KO/TKO']);
        Fight::factory()->for($finishes)->create(['bout_order' => 2, 'method' => 'Submission']);

        expect(featuredFightId($this->get('/')->getContent()))->toBe($judged->ufcstats_id);
    });

    it('counts split and majority decisions as going to the judges', function () {
        landingDecision(Event::factory()->create(['date' => '2026-09-01']), 1, 'Decision - Unanimous');
        $split = landingDecision(Event::factory()->create(['date' => '2026-09-08']), 1, 'Decision - Split');
        $majority = landingDecision(Event::factory()->create(['date' => '2026-09-15']), 1, 'Decision - Majority');

        $response = $this->get('/')->assertSee('Majority decision');
        expect(featuredFightId($response->getContent()))->toBe($majority->ufcstats_id);

        $majority->delete();
        $response = $this->get('/')->assertSee('Split decision');
        expect(featuredFightId($response->getContent()))->toBe($split->ufcstats_id);
    });
});

describe('with no judges\' decision in the database', function () {
    it('still loads with the nav, the closing section and the footer', function () {
        Fight::factory()->create(['method' => 'KO/TKO']);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Docs', 'GitHub', 'Get a free key'])
            ->assertSee('Every number on this page came from one request.')
            ->assertSee('Read the docs')
            ->assertSee('Not affiliated with the UFC or Zuffa LLC. Data from ufcstats.com.');
    });

    it('leaves out every fight section', function () {
        $this->get('/')->assertOk()
            ->assertDontSee('Red corner')
            ->assertDontSee('Blue corner')
            ->assertDontSee('Full fight')
            ->assertDontSee('Significant strikes')
            ->assertDontSee('Where the strikes landed')
            ->assertDontSee('Round by round')
            ->assertDontSee('ufcstats prints this fight');
    });

    it('drops the old typed-in Aliskerov vs Ferreira numbers', function () {
        $this->get('/')->assertOk()
            ->assertDontSee('Aliskerov')
            ->assertDontSee('Ferreira')
            ->assertDontSee('Lethaby')
            ->assertDontSee('30–27')
            ->assertDontSee('control_time_sec');
    });
});

describe('the tale of the tape', function () {
    beforeEach(function () {
        $this->app->instance(PageFetcher::class, new FakePageFetcher(base_path('tests/fixtures')));
        app(ScrapeUfc::class)->scrapeEvent('31e1ea6fe6b682f8', refreshFighters: true);
    });

    it('shows both corners, the result and the decision panel from the scraped bout', function () {
        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Red corner · W', 'Ikram', 'Aliskerov', 'Blue corner · L', 'Brunno', 'Ferreira'])
            ->assertSee('Unanimous decision')
            ->assertSee('30–27 · 30–27 · 30–27')
            ->assertSeeInOrder(['Middleweight', '3 × 5 min', 'Referee Marc Goddard', 'Judges Lethaby, Paolillo, Werner']);
    });

    it('offers one period button per round of the bout', function () {
        $this->get('/')
            ->assertSeeInOrder(['Full fight', 'Round 1', 'Round 2', 'Round 3'])
            ->assertDontSee('Round 4');
    });

    it('shows the six stat rows for the full fight', function () {
        $this->get('/')->assertSeeInOrder([
            '47', 'Significant strikes', '48% vs 35% accuracy', '19',
            '114', 'Total strikes', '23',
            '5/6', 'Takedowns', '0/1',
            '8:53', 'Control time', '0:07',
            '1', 'Submission attempts', '0',
            '0', 'Knockdowns', '0',
        ]);
    });

    it('shows strikes by target and position for the full fight', function () {
        $this->get('/')->assertSeeInOrder([
            'By target',
            'Aliskerov', 'Head 32', 'Body 9', 'Leg 6',
            'Ferreira', 'Head 13', 'Body 3', 'Leg 3',
            'By position',
            'Aliskerov', 'Distance 36', 'Clinch 0', 'Ground 11',
            'Ferreira', 'Distance 19', 'Clinch 0', 'Ground 0',
        ]);
    });

    it('shows significant strikes per round in the round-by-round chart', function () {
        $this->get('/')->assertSeeInOrder([
            'Round by round',
            '15', 'Round 1', '6',
            '20', 'Round 2', '10',
            '12', 'Round 3', '3',
        ]);
    });

    it('carries every round\'s numbers so the period buttons can switch them', function () {
        $periods = $this->get('/')->viewData('tape')['periods'];

        expect($periods)->toHaveCount(4)
            ->and(array_column($periods, 'label'))->toBe(['Full fight', 'Round 1', 'Round 2', 'Round 3'])
            ->and($periods[2]['rows'][0])->toMatchArray(['label' => 'Significant strikes', 'red' => '20', 'blue' => '10'])
            ->and($periods[3]['rows'][3])->toMatchArray(['label' => 'Control time', 'red' => '3:39', 'blue' => '0:00'])
            ->and($periods[1]['maps'][0]['corners'][0]['parts'][0])->toMatchArray(['label' => 'Head', 'value' => 9]);
    });

    it('closes with the one request that returns these numbers', function () {
        $this->get('/')
            ->assertSee('Every number on this page came from one request.')
            ->assertSee('ufcstats prints this fight as 27–30')
            ->assertSee('/v1/fights/d83294b031502177')
            ->assertSee('"control_time_sec": 533', false)
            ->assertSee('"landed": 15', false);
    });
});

it('shows a fighter\'s nickname after the first name', function () {
    $fight = landingDecision(Event::factory()->create(['date' => '2026-09-01']), 1, 'Decision - Unanimous');
    $fight->redFighter->update(['name' => 'Brunno Ferreira', 'nickname' => 'The Hulk']);

    $this->get('/')->assertSeeInOrder(['Brunno “The Hulk”', 'Ferreira']);
});

it('shows Round 1 to Round 5 for a five-round decision', function () {
    landingDecision(Event::factory()->create(['date' => '2026-09-01']), 1, 'Decision - Unanimous', rounds: 5);

    $this->get('/')
        ->assertSee('5 × 5 min')
        ->assertSeeInOrder(['Full fight', 'Round 1', 'Round 2', 'Round 3', 'Round 4', 'Round 5'])
        ->assertDontSee('Round 6');
});

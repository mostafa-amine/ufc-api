<?php

use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\RoundStat;
use App\Models\Scorecard;
use App\Models\User;
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
    it('still loads with the nav, the endpoints, the closing request and the footer', function () {
        Fight::factory()->create(['method' => 'KO/TKO']);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Docs', 'GitHub', 'Get a free key'])
            ->assertSee('Every UFC round,')
            ->assertSee('Read the docs')
            ->assertSeeInOrder(['/v1/events', '/v1/fights', '/v1/fighters', '/v1/health'])
            ->assertSee('/v1/fighters?search=silva')
            ->assertSee('Not affiliated with the UFC or Zuffa LLC. Data from ufcstats.com.');
    });

    it('leaves out the fight card and the response panel', function () {
        $this->get('/')->assertOk()
            ->assertDontSee('This fight card')
            ->assertDontSee('Red corner')
            ->assertDontSee('Blue corner')
            ->assertDontSee('Significant strikes')
            ->assertDontSee('200 OK')
            ->assertDontSee('ufcstats prints this fight');
    });
});

it('shows the live record counts on the endpoints', function () {
    Fight::factory()->count(2)->for(Event::factory()->create())->create(['method' => 'KO/TKO']);
    expect([Event::count(), Fight::count(), Fighter::count()])->toBe([1, 2, 4]);

    $this->get('/')->assertSeeInOrder(['1 event,', '2 fights.', '4 fighters,']);
});

describe('the fight card and its response', function () {
    beforeEach(function () {
        $this->app->instance(PageFetcher::class, new FakePageFetcher(base_path('tests/fixtures')));
        app(ScrapeUfc::class)->scrapeEvent('31e1ea6fe6b682f8', refreshFighters: true);
    });

    it('shows both corners, the result and the decision from the scraped bout', function () {
        $this->get('/')->assertOk()
            ->assertSee('This fight card')
            ->assertSeeInOrder(['Red corner · W', 'Ikram', 'Aliskerov', 'Blue corner · L', 'Brunno', 'Ferreira'])
            ->assertSee('Unanimous decision')
            ->assertSee('30–27 · 30–27 · 30–27')
            ->assertSeeInOrder(['Middleweight', '3 × 5 min', 'Referee Marc Goddard', 'Judges Lethaby, Paolillo, Werner']);
    });

    it('shows the four full-fight stat rows', function () {
        $this->get('/')->assertSeeInOrder([
            '47', 'Significant strikes', '48% vs 35% accuracy', '19',
            '114', 'Total strikes', '23',
            '5/6', 'Takedowns', '0/1',
            '8:53', 'Control time', '0:07',
        ]);
    });

    it('prints the real response beside the card, every value from the API', function () {
        $api = $this->getJson('/v1/fights/d83294b031502177', ['Authorization' => 'Bearer '.User::factory()->create()->createToken('t')->plainTextToken])->json('data');
        $red = $api['stats']['red']['total'];

        $this->get('/')
            ->assertSee('/v1/fights/d83294b031502177')
            ->assertSee('"method": "Decision - Unanimous",')
            ->assertSee('{ "judge": "David Lethaby", "red": 30, "blue": 27 }')
            ->assertSee('"sig_str": { "landed": '.$red['sig_str']['landed'].', "attempted": '.$red['sig_str']['attempted'].', "pct": '.$red['sig_str']['pct'].' },')
            ->assertSee('"control_time_sec": '.$red['control_time_sec'].',')
            ->assertSee('ufcstats prints this fight as 27–30');
    });

    it('ties each stat row to the response lines it comes from', function () {
        $html = $this->get('/')->getContent();

        foreach (['scores', 'sig', 'total', 'td', 'ctrl'] as $group) {
            expect($html)->toContain('data-group="'.$group.'"')
                ->and(substr_count($html, 'data-line="'.$group.'"'))->toBeGreaterThan(0);
        }
        // Significant strikes is picked on load: its row pressed and both corners' lines lit.
        expect($html)->toMatch('/data-group="sig" aria-pressed="true"/')
            ->and(preg_match_all('/class="line lit"\s+data-line="sig"/', $html))->toBe(2);
    });
});

it('shows a fighter\'s nickname after the first name', function () {
    $fight = landingDecision(Event::factory()->create(['date' => '2026-09-01']), 1, 'Decision - Unanimous');
    $fight->redFighter->update(['name' => 'Brunno Ferreira', 'nickname' => 'The Hulk']);

    $this->get('/')->assertSeeInOrder(['Brunno “The Hulk”', 'Ferreira']);
});

it('shows an unknown control time as a dash with no bar, and null in the response', function () {
    $fight = landingDecision(Event::factory()->create(['date' => '2026-09-01']), 1, 'Decision - Unanimous');
    RoundStat::where(['fight_id' => $fight->id, 'fighter_id' => $fight->red_fighter_id, 'round' => 0])->update(['control_time_sec' => 100]);
    RoundStat::where(['fight_id' => $fight->id, 'fighter_id' => $fight->blue_fighter_id, 'round' => 0])->update(['control_time_sec' => null]);

    $response = $this->get('/');
    $control = collect($response->viewData('tape')['rows'])->keyBy('label')['Control time'];

    expect($control)->toMatchArray([
        'red' => '1:40', 'red_width' => 100,
        'blue' => '–', 'blue_width' => 0, 'blue_leads' => false,
    ]);
    $response->assertSee('"control_time_sec": null,');
});

it('shows a corner with no fight totals as dashes and a null total', function () {
    $fight = landingDecision(Event::factory()->create(['date' => '2026-09-01']), 1, 'Decision - Unanimous');
    RoundStat::where(['fight_id' => $fight->id, 'fighter_id' => $fight->blue_fighter_id, 'round' => 0])->delete();

    $response = $this->get('/')->assertOk();

    foreach ($response->viewData('tape')['rows'] as $row) {
        expect($row['blue'])->toBe('–')->and($row['blue_width'])->toBe(0)->and($row['red_leads'])->toBeFalse();
    }
    $response->assertSee('"total": null,');
});

it('draws neither side as the leader on a tie, 0–0 included', function () {
    $fight = landingDecision(Event::factory()->create(['date' => '2026-09-01']), 1, 'Decision - Unanimous');
    $totals = fn (int $fighterId) => RoundStat::where(['fight_id' => $fight->id, 'fighter_id' => $fighterId, 'round' => 0]);
    $totals($fight->red_fighter_id)->update(['takedowns_landed' => 0, 'takedowns_attempted' => 0, 'sig_str_landed' => 30, 'total_str_landed' => 50]);
    $totals($fight->blue_fighter_id)->update(['takedowns_landed' => 0, 'takedowns_attempted' => 0, 'sig_str_landed' => 30, 'total_str_landed' => 40]);

    $rows = collect($this->get('/')->viewData('tape')['rows'])->keyBy('label');

    expect($rows['Takedowns'])->toMatchArray(['red' => '0/0', 'blue' => '0/0', 'red_leads' => false, 'blue_leads' => false])
        ->and($rows['Significant strikes'])->toMatchArray(['red' => '30', 'blue' => '30', 'red_leads' => false, 'blue_leads' => false])
        ->and($rows['Total strikes'])->toMatchArray(['red_leads' => true, 'blue_leads' => false]);
});

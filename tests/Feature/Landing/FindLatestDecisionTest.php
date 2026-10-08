<?php

use App\Models\Event;
use App\Models\Fight;
use App\Models\RoundStat;
use App\Models\Scorecard;
use App\Services\Landing\FindLatestDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A decision with everything the landing page needs: cards, both fighters, rounds for both corners. */
function completeDecision(Event $event, ?int $boutOrder, string $method = 'Decision - Unanimous'): Fight
{
    $fight = Fight::factory()->for($event)->create(['bout_order' => $boutOrder, 'method' => $method]);
    foreach ([$fight->red_fighter_id, $fight->blue_fighter_id] as $fighterId) {
        foreach ([0, 1, 2, 3] as $round) {
            RoundStat::factory()->create(['fight_id' => $fight->id, 'fighter_id' => $fighterId, 'round' => $round]);
        }
    }
    Scorecard::factory()->count(3)->create(['fight_id' => $fight->id]);

    return $fight;
}

it('picks the decision closest to the main event on the latest card', function () {
    $older = Event::factory()->create(['date' => '2026-09-01']);
    completeDecision($older, 1);

    $latest = Event::factory()->create(['date' => '2026-09-20']);
    Fight::factory()->for($latest)->create(['bout_order' => 1, 'method' => 'KO/TKO']);
    completeDecision($latest, 8);
    $bout2 = completeDecision($latest, 2, 'Decision - Split');
    completeDecision($latest, 5, 'Decision - Majority');

    expect(app(FindLatestDecision::class)()->id)->toBe($bout2->id);
    expect($this->get('/')->assertOk()->viewData('tape')['id'])->toBe($bout2->ufcstats_id);
});

it('ranks a decision with no card position after the numbered ones', function () {
    $event = Event::factory()->create(['date' => '2026-09-20']);
    completeDecision($event, null);
    $numbered = completeDecision($event, 6);

    expect(app(FindLatestDecision::class)()->id)->toBe($numbered->id);
});

it('skips a newer event whose bouts have no result yet', function () {
    $judged = completeDecision(Event::factory()->create(['date' => '2026-09-01']), 3);
    $upcoming = Event::factory()->create(['date' => '2026-10-20']);
    Fight::factory()->for($upcoming)->create(['bout_order' => 1, 'method' => null, 'outcome' => 'pending']);

    expect(app(FindLatestDecision::class)()->id)->toBe($judged->id);
});

it('finds nothing when no bout went to the judges', function () {
    Fight::factory()->create(['method' => 'KO/TKO']);
    Fight::factory()->create(['method' => 'Submission']);

    expect(app(FindLatestDecision::class)())->toBeNull();
    expect($this->get('/')->assertOk()->viewData('tape'))->toBeNull();
});

describe('a newer decision with incomplete data is skipped for the older complete one', function () {
    beforeEach(function () {
        $this->complete = completeDecision(Event::factory()->create(['date' => '2026-09-01']), 5);
        $this->newer = completeDecision(Event::factory()->create(['date' => '2026-09-20']), 1);
    });

    afterEach(function () {
        expect(app(FindLatestDecision::class)()->id)->toBe($this->complete->id)
            ->and($this->get('/')->assertOk()->viewData('tape')['id'])->toBe($this->complete->ufcstats_id);
    });

    it('when it has no scorecards', function () {
        $this->newer->scorecards()->delete();
    });

    it('when blue has no round stats', function () {
        RoundStat::where(['fight_id' => $this->newer->id, 'fighter_id' => $this->newer->blue_fighter_id])->delete();
    });

    it('when red has only the fight totals, no rounds', function () {
        RoundStat::where(['fight_id' => $this->newer->id, 'fighter_id' => $this->newer->red_fighter_id])->where('round', '>', 0)->delete();
    });

    it('when a fighter is missing', function () {
        $this->newer->update(['blue_fighter_id' => null]);
    });
});

it('shows no fight when the only decision is incomplete', function () {
    completeDecision(Event::factory()->create(['date' => '2026-09-20']), 1)->scorecards()->delete();

    expect(app(FindLatestDecision::class)())->toBeNull();
    $this->get('/')->assertOk()->assertDontSee('Round by round');
    expect($this->get('/')->viewData('tape'))->toBeNull();
});

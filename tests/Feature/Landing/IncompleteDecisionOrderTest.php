<?php

use App\Models\Event;
use App\Models\Fight;
use App\Models\RoundStat;
use App\Models\Scorecard;
use App\Services\Landing\FindLatestDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('falls to the next decision on the same card when the highest one is incomplete', function () {
    $event = Event::factory()->create(['date' => '2026-09-20']);
    $decision = function (int $boutOrder, bool $withCards) use ($event): Fight {
        $fight = Fight::factory()->for($event)->create(['bout_order' => $boutOrder, 'method' => 'Decision - Unanimous']);
        foreach ([$fight->red_fighter_id, $fight->blue_fighter_id] as $fighterId) {
            foreach ([0, 1, 2, 3] as $round) {
                RoundStat::factory()->create(['fight_id' => $fight->id, 'fighter_id' => $fighterId, 'round' => $round]);
            }
        }
        if ($withCards) {
            Scorecard::factory()->count(3)->create(['fight_id' => $fight->id]);
        }

        return $fight;
    };
    $decision(2, withCards: false);
    $bout5 = $decision(5, withCards: true);
    $decision(8, withCards: true);
    $older = Event::factory()->create(['date' => '2026-09-01']);
    Fight::factory()->for($older)->create(['bout_order' => 1, 'method' => 'Decision - Unanimous']);

    expect(app(FindLatestDecision::class)()->id)->toBe($bout5->id)
        ->and($this->get('/')->assertOk()->viewData('tape')['id'])->toBe($bout5->ufcstats_id);
});

<?php

use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\RoundStat;
use App\Models\Scorecard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds a fully related fight graph', function () {
    $fight = Fight::factory()->titleBout()->create();

    expect($fight->event)->toBeInstanceOf(Event::class)
        ->and($fight->redFighter)->toBeInstanceOf(Fighter::class)
        ->and($fight->blueFighter)->toBeInstanceOf(Fighter::class)
        ->and($fight->winner->id)->toBe($fight->red_fighter_id)
        ->and($fight->is_title_bout)->toBeTrue()
        ->and($fight->scheduled_rounds)->toBe(5);

    RoundStat::factory()->total()->create([
        'fight_id' => $fight->id, 'fighter_id' => $fight->red_fighter_id,
    ]);
    RoundStat::factory()->round(1)->create([
        'fight_id' => $fight->id, 'fighter_id' => $fight->red_fighter_id,
    ]);
    Scorecard::factory()->count(3)->create(['fight_id' => $fight->id]);

    $fight->refresh()->load('roundStats', 'scorecards');

    expect($fight->roundStats)->toHaveCount(2)
        ->and($fight->roundStats->first()->round)->toBe(0) // ordered: total (round 0) first
        ->and($fight->scorecards)->toHaveCount(3);
});

it('enforces the (fight, fighter, round) uniqueness', function () {
    $fight = Fight::factory()->create();
    $args = ['fight_id' => $fight->id, 'fighter_id' => $fight->red_fighter_id, 'round' => 1];

    RoundStat::factory()->create($args);

    expect(fn () => RoundStat::factory()->create($args))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('resolves route binding by ufcstats_id', function () {
    expect(Event::factory()->create()->getRouteKeyName())->toBe('ufcstats_id')
        ->and(Fighter::factory()->create()->getRouteKeyName())->toBe('ufcstats_id')
        ->and(Fight::factory()->create()->getRouteKeyName())->toBe('ufcstats_id');
});

it('queries all fights for a fighter across both corners', function () {
    $fighter = Fighter::factory()->create();
    Fight::factory()->create(['red_fighter_id' => $fighter->id]);
    Fight::factory()->create(['blue_fighter_id' => $fighter->id]);

    expect($fighter->fights()->count())->toBe(2);
});

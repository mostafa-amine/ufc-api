<?php

use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\RoundStat;
use App\Models\Scorecard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(User::factory()->create()));

/* ----------------------------------------------------------------- Events */

it('lists events with pagination metadata', function () {
    Event::factory()->count(3)->create();

    $this->getJson('/v1/events')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'date', 'location', 'status', 'url']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'total', 'per_page'],
        ])
        ->assertJsonCount(3, 'data');
});

it('filters events by status and search', function () {
    Event::factory()->create(['name' => 'UFC 300', 'status' => 'completed']);
    Event::factory()->upcoming()->create(['name' => 'UFC 999']);

    $this->getJson('/v1/events?status=upcoming')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/v1/events?search=300')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'UFC 300');
});

it('shows an event with its bouts', function () {
    $event = Event::factory()->create();
    Fight::factory()->count(2)->create(['event_id' => $event->id]);

    $this->getJson("/v1/events/{$event->ufcstats_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $event->ufcstats_id)
        ->assertJsonCount(2, 'data.fights')
        ->assertJsonStructure(['data' => ['location' => ['raw', 'city', 'state', 'country'], 'fights']]);
});

/* --------------------------------------------------------------- Fighters */

it('shows a fighter profile', function () {
    $fighter = Fighter::factory()->create(['name' => 'Jon Jones', 'height_in' => 76]);

    $this->getJson("/v1/fighters/{$fighter->ufcstats_id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Jon Jones')
        ->assertJsonPath('data.physical.height_in', 76)
        ->assertJsonStructure(['data' => ['record' => ['wins', 'losses'], 'stats' => ['slpm', 'td_def']]]);
});

it('searches fighters by name', function () {
    Fighter::factory()->create(['name' => 'Israel Adesanya']);
    Fighter::factory()->create(['name' => 'Alex Pereira']);

    $this->getJson('/v1/fighters?search=Adesanya')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Israel Adesanya');
});

it("lists a fighter's bout history newest first", function () {
    $fighter = Fighter::factory()->create();
    $old = Event::factory()->create(['date' => '2015-01-01']);
    $recent = Event::factory()->create(['date' => '2024-01-01']);
    Fight::factory()->create(['event_id' => $old->id, 'red_fighter_id' => $fighter->id]);
    Fight::factory()->create(['event_id' => $recent->id, 'blue_fighter_id' => $fighter->id]);

    $res = $this->getJson("/v1/fighters/{$fighter->ufcstats_id}/fights")->assertOk();
    expect($res->json('data'))->toHaveCount(2)
        ->and($res->json('data.0.event.date'))->toBe('2024-01-01');
});

/* ----------------------------------------------------------------- Fights */

it('returns a full fight with derived percentages, rounds and scorecards', function () {
    $fight = Fight::factory()->create();

    RoundStat::factory()->total()->create([
        'fight_id' => $fight->id, 'fighter_id' => $fight->red_fighter_id,
        'sig_str_landed' => 47, 'sig_str_attempted' => 98,
    ]);
    RoundStat::factory()->round(1)->create([
        'fight_id' => $fight->id, 'fighter_id' => $fight->red_fighter_id,
    ]);
    RoundStat::factory()->total()->create([
        'fight_id' => $fight->id, 'fighter_id' => $fight->blue_fighter_id,
    ]);
    Scorecard::factory()->count(3)->create(['fight_id' => $fight->id]);

    $res = $this->getJson("/v1/fights/{$fight->ufcstats_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $fight->ufcstats_id)
        ->assertJsonPath('data.red.outcome', 'win')
        ->assertJsonPath('data.blue.outcome', 'loss')
        ->assertJsonPath('data.stats.red.total.sig_str.landed', 47)
        ->assertJsonPath('data.stats.red.total.sig_str.pct', 48) // 47/98
        ->assertJsonCount(1, 'data.stats.red.rounds')
        ->assertJsonCount(3, 'data.scorecards')
        ->assertJsonStructure(['data' => ['stats' => ['red' => ['total' => ['targets' => ['head'], 'positions' => ['ground']]]]]]);

    expect($res->json('data.scorecards.0'))->toHaveKeys(['judge', 'red', 'blue']);
});

it('filters fights by title bout and fighter', function () {
    $fighter = Fighter::factory()->create();
    Fight::factory()->titleBout()->create(['red_fighter_id' => $fighter->id]);
    Fight::factory()->create();

    $this->getJson('/v1/fights?is_title_bout=1')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/v1/fights?fighter_id={$fighter->ufcstats_id}")->assertOk()->assertJsonCount(1, 'data');
});

/* ------------------------------------------------------------ Meta + errors */

it('reports health', function () {
    Event::factory()->count(2)->create();

    $this->getJson('/v1/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.counts.events', 2);
});

it('returns JSON 404 for an unknown fight', function () {
    $this->getJson('/v1/fights/ffffffffffffffff')->assertNotFound();
});

it('validates query parameters', function () {
    $this->getJson('/v1/events?status=bogus')->assertStatus(422);
});

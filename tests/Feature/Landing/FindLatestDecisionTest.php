<?php

use App\Models\Event;
use App\Models\Fight;
use App\Services\Landing\FindLatestDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('picks the decision closest to the main event on the latest card', function () {
    $older = Event::factory()->create(['date' => '2026-09-01']);
    Fight::factory()->for($older)->create(['bout_order' => 1, 'method' => 'Decision - Unanimous']);

    $latest = Event::factory()->create(['date' => '2026-09-20']);
    Fight::factory()->for($latest)->create(['bout_order' => 1, 'method' => 'KO/TKO']);
    Fight::factory()->for($latest)->create(['bout_order' => 8, 'method' => 'Decision - Unanimous']);
    $bout2 = Fight::factory()->for($latest)->create(['bout_order' => 2, 'method' => 'Decision - Split']);
    Fight::factory()->for($latest)->create(['bout_order' => 5, 'method' => 'Decision - Majority']);

    expect(app(FindLatestDecision::class)()->id)->toBe($bout2->id);
    expect($this->get('/')->assertOk()->viewData('tape')['id'])->toBe($bout2->ufcstats_id);
});

it('ranks a decision with no card position after the numbered ones', function () {
    $event = Event::factory()->create(['date' => '2026-09-20']);
    Fight::factory()->for($event)->create(['bout_order' => null, 'method' => 'Decision - Unanimous']);
    $numbered = Fight::factory()->for($event)->create(['bout_order' => 6, 'method' => 'Decision - Unanimous']);

    expect(app(FindLatestDecision::class)()->id)->toBe($numbered->id);
});

it('skips a newer event whose bouts have no result yet', function () {
    $judged = Fight::factory()->for(Event::factory()->create(['date' => '2026-09-01']))
        ->create(['bout_order' => 3, 'method' => 'Decision - Unanimous']);
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

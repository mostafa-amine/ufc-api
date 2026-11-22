<?php

use App\Models\Event;
use App\Models\ScrapeRun;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function run(string $status, $finishedAt): ScrapeRun
{
    return ScrapeRun::create([
        'type' => 'incremental', 'status' => $status,
        'started_at' => $finishedAt->copy()->subMinutes(5), 'finished_at' => $finishedAt,
    ]);
}

it('says ok while the data was updated in the last day', function () {
    run('success', now()->subHours(3));

    $this->getJson('/v1/health')->assertOk()->assertJsonPath('data.status', 'ok');
});

it('counts a partial update as an update, since it still saved new data', function () {
    run('partial', now()->subHours(3));

    $this->getJson('/v1/health')->assertJsonPath('data.status', 'ok');
});

it('says stale once no update worked for over a day', function () {
    run('success', now()->subHours(30));

    $this->getJson('/v1/health')->assertJsonPath('data.status', 'stale');
});

it('says stale when updates keep failing, and still shows when the data was last good', function () {
    $good = run('success', now()->subDays(3));
    run('failed', now()->subHours(2));

    $this->getJson('/v1/health')
        ->assertJsonPath('data.status', 'stale')
        ->assertJsonPath('data.last_scrape.status', 'failed')
        ->assertJsonPath('data.last_successful_scrape.finished_at', $good->finished_at->toIso8601String());
});

it('says stale before any update ever ran', function () {
    $this->getJson('/v1/health')
        ->assertJsonPath('data.status', 'stale')
        ->assertJsonPath('data.last_successful_scrape', null)
        ->assertJsonPath('data.latest_event', null);
});

it('shows the latest card that already happened and how many of its fights have results', function () {
    Event::factory()->create(['date' => now()->addDays(7)->toDateString()]); // upcoming, not shown
    Event::factory()->create(['date' => now()->subDays(8)->toDateString()]); // older, not shown
    $latest = Event::factory()->create(['date' => now()->subDay()->toDateString()]);
    $latest->fights()->createMany([
        ['ufcstats_id' => 'f000000000000001', 'outcome' => 'win'],
        ['ufcstats_id' => 'f000000000000002', 'outcome' => 'draw'],
        ['ufcstats_id' => 'f000000000000003', 'outcome' => 'pending'],
    ]);

    $this->getJson('/v1/health')->assertJsonPath('data.latest_event', [
        'id' => $latest->ufcstats_id,
        'name' => $latest->name,
        'date' => $latest->date->toDateString(),
        'fights' => 3,
        'fights_with_results' => 2,
    ]);
});

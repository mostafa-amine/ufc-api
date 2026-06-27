<?php

use App\Scraping\Parsers\EventDetailParser;
use App\Scraping\Parsers\EventListParser;
use App\Scraping\Parsers\FighterParser;
use App\Scraping\Parsers\FightDetailParser;

function ufcFixture(string $name): string
{
    return file_get_contents(__DIR__.'/../../fixtures/'.$name);
}

/* ---------------------------------------------------------------- Event list */

it('parses the completed events list', function () {
    $events = (new EventListParser)->parse(ufcFixture('events_completed.html'));

    expect(count($events))->toBeGreaterThan(700);

    $first = $events[0];
    expect($first['ufcstats_id'])->toBe('31e1ea6fe6b682f8')
        ->and($first['name'])->toBe('UFC Fight Night: Fiziev vs. Torres')
        ->and($first['date'])->toBe('2026-06-27')
        ->and($first['location_raw'])->toBe('Baku, Azerbaijan');
});

/* -------------------------------------------------------------- Event detail */

it('parses an event page into meta + bouts', function () {
    $data = (new EventDetailParser)->parse(ufcFixture('event_detail.html'));

    expect($data['event']['name'])->toBe('UFC Fight Night: Fiziev vs. Torres')
        ->and($data['event']['date'])->toBe('2026-06-27')
        ->and($data['event']['location_raw'])->toBe('Baku, Azerbaijan')
        ->and($data['bouts'])->toHaveCount(13);

    $ids = array_column($data['bouts'], 'ufcstats_id');
    expect($ids)->toContain('d83294b031502177');

    $decision = collect($data['bouts'])->firstWhere('ufcstats_id', 'd83294b031502177');
    expect($decision['fighters'])->toHaveCount(2)
        ->and(array_column($decision['fighters'], 'name'))
        ->toBe(['Ikram Aliskerov', 'Brunno Ferreira']);
});

/* -------------------------------------------------------------------- Fighter */

it('parses a fighter profile', function () {
    $f = (new FighterParser)->parse(ufcFixture('fighter_c814b4c899793af6.html'));

    expect($f['name'])->toBe('Rafael Fiziev')
        ->and($f['height_in'])->toBe(68)
        ->and($f['weight_lb'])->toBe(155)
        ->and($f['reach_in'])->toBe(71.0)
        ->and($f['stance'])->toBe('Switch')
        ->and($f['dob'])->toBe('1993-03-05')
        ->and($f['wins'])->toBe(13)
        ->and($f['losses'])->toBe(5)
        ->and($f['slpm'])->toBe(4.71)
        ->and($f['str_acc'])->toBe(52.0)
        ->and($f['td_def'])->toBe(90.0)
        ->and($f['sub_avg'])->toBe(0.0);
});

/* --------------------------------------------------------- Fight: decision */

it('parses a decision fight with corners, result and metadata', function () {
    $f = (new FightDetailParser)->parse(ufcFixture('fight_d83294b031502177.html'));

    expect($f['persons'][0])->toMatchArray(['corner' => 'red', 'status' => 'W', 'name' => 'Ikram Aliskerov'])
        ->and($f['persons'][1])->toMatchArray(['corner' => 'blue', 'status' => 'L', 'name' => 'Brunno Ferreira'])
        ->and($f['outcome'])->toBe('win')
        ->and($f['winner_corner'])->toBe('red')
        ->and($f['method'])->toBe('Decision - Unanimous')
        ->and($f['method_detail'])->toBeNull()
        ->and($f['end_round'])->toBe(3)
        ->and($f['end_time_sec'])->toBe(300)
        ->and($f['time_format_raw'])->toBe('3 Rnd (5-5-5)')
        ->and($f['scheduled_rounds'])->toBe(3)
        ->and($f['referee'])->toBe('Marc Goddard');
});

it('maps judge scores to the winner corner, not by column position', function () {
    $f = (new FightDetailParser)->parse(ufcFixture('fight_d83294b031502177.html'));

    // Winner is red (Aliskerov), who dominated. ufcstats prints "27 - 30":
    // 30 (second) is the winner's score, so red_score = 30, blue_score = 27.
    expect($f['scorecards'])->toHaveCount(3);
    foreach ($f['scorecards'] as $card) {
        expect($card['red_score'])->toBe(30)
            ->and($card['blue_score'])->toBe(27);
    }
    expect(array_column($f['scorecards'], 'judge'))
        ->toBe(['David Lethaby', 'Vito Paolillo', 'Clemens Werner']);
});

it('parses full totals + per-round + strike-target stats', function () {
    $f = (new FightDetailParser)->parse(ufcFixture('fight_d83294b031502177.html'));

    $redTotal = $f['stats']['red'][0];
    expect($redTotal)->toMatchArray([
        'round' => 0,
        'knockdowns' => 0,
        'sig_str_landed' => 47, 'sig_str_attempted' => 98,
        'total_str_landed' => 114, 'total_str_attempted' => 179,
        'takedowns_landed' => 5, 'takedowns_attempted' => 6,
        'sub_attempts' => 1, 'reversals' => 0,
        'control_time_sec' => 533, // 8:53
        'head_landed' => 32, 'head_attempted' => 80,
        'body_landed' => 9, 'leg_landed' => 6,
        'distance_landed' => 36, 'clinch_landed' => 0, 'ground_landed' => 11,
    ]);

    $blueTotal = $f['stats']['blue'][0];
    expect($blueTotal)->toMatchArray([
        'sig_str_landed' => 19, 'sig_str_attempted' => 55,
        'control_time_sec' => 7, // 0:07
    ]);

    // Per-round present for 3 rounds.
    expect($f['stats']['red'])->toHaveKeys([0, 1, 2, 3]);
    expect($f['stats']['red'][1])->toMatchArray([
        'round' => 1,
        'sig_str_landed' => 15, 'sig_str_attempted' => 29,
        'control_time_sec' => 164, // 2:44
        'head_landed' => 9, 'distance_landed' => 14,
    ]);
});

/* ------------------------------------------------------------- Fight: finish */

it('parses a KO finish with no scorecards and a finish detail', function () {
    $f = (new FightDetailParser)->parse(ufcFixture('fight_809814f03ff3140c.html'));

    expect($f['persons'][0])->toMatchArray(['corner' => 'red', 'status' => 'L', 'name' => 'Nazim Sadykhov'])
        ->and($f['persons'][1])->toMatchArray(['corner' => 'blue', 'status' => 'W', 'name' => 'Matheus Camilo'])
        ->and($f['winner_corner'])->toBe('blue')
        ->and($f['method'])->toBe('KO/TKO')
        ->and($f['method_detail'])->toBe('Punch to Head At Distance')
        ->and($f['end_round'])->toBe(1)
        ->and($f['end_time_sec'])->toBe(91) // 1:31
        ->and($f['scorecards'])->toBe([]);
});

it('maps scores to a blue-corner winner correctly', function () {
    $f = (new FightDetailParser)->parse(ufcFixture('fight_decision2_856f6ee049373843.html'));

    // Winner is blue (Makhachev); "45 - 50" => blue_score 50, red_score 45.
    expect($f['winner_corner'])->toBe('blue');
    foreach ($f['scorecards'] as $card) {
        expect($card['red_score'])->toBe(45)
            ->and($card['blue_score'])->toBe(50);
    }
});

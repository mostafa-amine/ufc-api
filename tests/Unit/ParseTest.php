<?php

use App\Scraping\Support\Parse;

it('parses physical measurements', function () {
    expect(Parse::heightToInches("5' 8\""))->toBe(68)
        ->and(Parse::heightToInches('--'))->toBeNull()
        ->and(Parse::weightToLbs('155 lbs.'))->toBe(155)
        ->and(Parse::reachToInches('71"'))->toBe(71.0)
        ->and(Parse::reachToInches('--'))->toBeNull();
});

it('parses dates to Y-m-d', function () {
    expect(Parse::date('Mar 05, 1993'))->toBe('1993-03-05')
        ->and(Parse::date('June 27, 2026'))->toBe('2026-06-27')
        ->and(Parse::date('--'))->toBeNull();
});

it('parses fighter records including no-contests', function () {
    expect(Parse::record('Record: 13-5-0'))
        ->toBe(['wins' => 13, 'losses' => 5, 'draws' => 0, 'no_contests' => 0]);

    expect(Parse::record('Record: 24-1-0 (1 NC)'))
        ->toBe(['wins' => 24, 'losses' => 1, 'draws' => 0, 'no_contests' => 1]);
});

it('parses landed/attempted pairs', function () {
    expect(Parse::landedAttempted('47 of 98'))->toBe(['landed' => 47, 'attempted' => 98])
        ->and(Parse::landedAttempted('---'))->toBe(['landed' => 0, 'attempted' => 0]);
});

it('parses percentages and decimals', function () {
    expect(Parse::percent('52%'))->toBe(52.0)
        ->and(Parse::percent('---'))->toBeNull()
        ->and(Parse::decimal('4.71'))->toBe(4.71)
        ->and(Parse::decimal('0.0'))->toBe(0.0);
});

it('parses clock strings to seconds', function () {
    expect(Parse::clockToSeconds('8:53'))->toBe(533)
        ->and(Parse::clockToSeconds('0:07'))->toBe(7)
        ->and(Parse::clockToSeconds('--'))->toBeNull();
});

it('derives scheduled rounds from time format', function () {
    expect(Parse::scheduledRounds('3 Rnd (5-5-5)'))->toBe(3)
        ->and(Parse::scheduledRounds('5 Rnd (5-5-5-5-5)'))->toBe(5)
        ->and(Parse::scheduledRounds('No Time Limit'))->toBeNull();
});

it('extracts ufcstats ids from urls', function () {
    expect(Parse::idFromUrl('http://ufcstats.com/fighter-details/c814b4c899793af6'))->toBe('c814b4c899793af6')
        ->and(Parse::idFromUrl('http://ufcstats.com/event-details/31e1ea6fe6b682f8'))->toBe('31e1ea6fe6b682f8')
        ->and(Parse::idFromUrl('garbage'))->toBeNull();
});

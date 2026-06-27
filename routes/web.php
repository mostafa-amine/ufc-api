<?php

use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\RoundStat;
use App\Support\Format;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing', [
        'counts' => [
            'events' => Event::count(),
            'fighters' => Fighter::count(),
            'fights' => Fight::count(),
            'rounds' => RoundStat::where('round', '>', 0)->count(),
        ],
        'featured' => featuredFight(),
    ]);
});

/**
 * Real data for the "Mapped right" scorecard showcase. Returns null (and the view
 * falls back to static values) when the featured bout hasn't been scraped yet.
 */
function featuredFight(): ?array
{
    $fight = Fight::with(['redFighter', 'blueFighter', 'scorecards', 'event'])
        ->where('ufcstats_id', 'd83294b031502177') // Aliskerov vs Ferreira
        ->first();

    if (! $fight || $fight->scorecards->isEmpty() || ! $fight->redFighter || ! $fight->blueFighter) {
        return null;
    }

    $total = fn (?int $fighterId) => RoundStat::where('fight_id', $fight->id)
        ->where('fighter_id', $fighterId)->where('round', 0)->first();
    $red = $total($fight->red_fighter_id);
    $blue = $total($fight->blue_fighter_id);
    $card = $fight->scorecards->first();
    $last = fn (string $name) => last(explode(' ', $name));

    return [
        'red_last' => $last($fight->redFighter->name),
        'blue_last' => $last($fight->blueFighter->name),
        'red_score' => $card->red_score,
        'blue_score' => $card->blue_score,
        'red_sig' => $red?->sig_str_landed,
        'blue_sig' => $blue?->sig_str_landed,
        'control' => $red ? Format::clock($red->control_time_sec) : null,
        'method' => $fight->method,
        'event' => $fight->event?->name,
        'cards' => $fight->scorecards->map(fn ($c) => [
            'judge' => $c->judge_name, 'red' => $c->red_score, 'blue' => $c->blue_score,
        ])->all(),
    ];
}

Route::get('/docs', fn () => view('docs'));

Route::get('/docs/openapi.yaml', function () {
    $path = storage_path('app/private/scribe/openapi.yaml');
    abort_unless(is_file($path), 404);

    return response()->file($path, ['Content-Type' => 'application/yaml']);
});

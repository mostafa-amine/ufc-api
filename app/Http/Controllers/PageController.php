<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\RoundStat;
use App\Support\Format;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    /** Marketing landing page. */
    public function home(): View
    {
        return view('landing', [
            'counts' => [
                'events' => Event::count(),
                'fighters' => Fighter::count(),
                'fights' => Fight::count(),
                'rounds' => RoundStat::where('round', '>', 0)->count(),
            ],
            'featured' => $this->featuredFight(),
        ]);
    }

    /** Interactive API reference (RapiDoc). */
    public function docs(): View
    {
        return view('docs');
    }

    /** Serve the Scribe-generated OpenAPI spec consumed by the docs page. */
    public function openapi(): Response
    {
        $path = storage_path('app/private/scribe/openapi.yaml');
        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, ['Content-Type' => 'application/yaml']);
    }

    /**
     * Real data for the "Mapped right" scorecard showcase. Returns null (and the
     * view falls back to static values) when the featured bout isn't scraped yet.
     */
    private function featuredFight(): ?array
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
}

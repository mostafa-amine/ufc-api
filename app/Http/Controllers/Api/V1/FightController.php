<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FightResource;
use App\Http\Resources\FightSummaryResource;
use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use Illuminate\Http\Request;

class FightController extends Controller
{
    /** List fights */
    public function index(Request $request)
    {
        $request->validate([
            'event_id' => 'string|size:16',
            'fighter_id' => 'string|size:16',
            'method' => 'string|max:50',
            'weight_class' => 'string|max:50',
            'is_title_bout' => 'boolean',
            'per_page' => 'integer|min:1|max:100',
        ]);

        $fights = Fight::query()
            ->select('fights.*')
            ->leftJoin('events', 'events.id', '=', 'fights.event_id')
            ->with(['redFighter', 'blueFighter', 'event'])
            ->when($request->event_id, function ($q, $id) {
                $q->where('fights.event_id', Event::where('ufcstats_id', $id)->value('id') ?? 0);
            })
            ->when($request->fighter_id, function ($q, $id) {
                $fighterId = Fighter::where('ufcstats_id', $id)->value('id') ?? 0;
                $q->where(fn ($w) => $w
                    ->where('fights.red_fighter_id', $fighterId)
                    ->orWhere('fights.blue_fighter_id', $fighterId));
            })
            ->when($request->method, fn ($q, $m) => $q->where('fights.method', 'like', "%{$m}%"))
            ->when($request->weight_class, fn ($q, $w) => $q->where('fights.weight_class', $w))
            ->when($request->filled('is_title_bout'), fn ($q) => $q->where('fights.is_title_bout', $request->boolean('is_title_bout')))
            ->orderByDesc('events.date')
            ->orderBy('fights.bout_order')
            ->paginate($request->integer('per_page', 25));

        return FightSummaryResource::collection($fights);
    }

    /** Get a fight */
    public function show(Fight $fight)
    {
        $fight->load(['event', 'redFighter', 'blueFighter', 'winner', 'roundStats', 'scorecards']);

        return new FightResource($fight);
    }
}

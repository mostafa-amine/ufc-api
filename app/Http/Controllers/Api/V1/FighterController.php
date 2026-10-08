<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FighterResource;
use App\Http\Resources\FightSummaryResource;
use App\Models\Fight;
use App\Models\Fighter;
use Illuminate\Http\Request;

class FighterController extends Controller
{
    /** List fighters */
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'string|max:100',
            'stance' => 'string|max:30',
            'per_page' => 'integer|min:1|max:100',
            'sort' => 'in:name,-name,wins,-wins,losses,-losses',
        ]);

        $fighters = Fighter::query()
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('nickname', 'like', "%{$s}%")))
            ->when($request->stance, fn ($q, $s) => $q->where('stance', $s))
            ->tap(fn ($q) => $this->applySort($q, $request->input('sort', 'name')))
            ->paginate($request->integer('per_page', 25));

        return FighterResource::collection($fighters);
    }

    /** Get a fighter */
    public function show(Fighter $fighter)
    {
        return new FighterResource($fighter);
    }

    /** Get a fighter's fights */
    public function fights(Request $request, Fighter $fighter)
    {
        $fights = Fight::query()
            ->select('fights.*')
            ->leftJoin('events', 'events.id', '=', 'fights.event_id')
            ->where(fn ($q) => $q
                ->where('fights.red_fighter_id', $fighter->id)
                ->orWhere('fights.blue_fighter_id', $fighter->id))
            ->with(['redFighter', 'blueFighter', 'event'])
            ->orderByDesc('events.date')
            ->paginate($request->integer('per_page', 25));

        return FightSummaryResource::collection($fights);
    }

    private function applySort($query, string $sort): void
    {
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $query->orderBy(ltrim($sort, '-'), $dir);
    }
}

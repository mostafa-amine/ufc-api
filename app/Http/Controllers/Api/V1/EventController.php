<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventSummaryResource;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /** List events */
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'in:upcoming,completed',
            'from' => 'date',
            'to' => 'date',
            'search' => 'string|max:100',
            'per_page' => 'integer|min:1|max:100',
            'sort' => 'in:date,-date,name,-name',
        ]);

        $events = Event::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->from, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->tap(fn ($q) => $this->applySort($q, $request->input('sort', '-date')))
            ->paginate($request->integer('per_page', 25));

        return EventSummaryResource::collection($events);
    }

    /** Get an event */
    public function show(Event $event)
    {
        $event->load(['fights.redFighter', 'fights.blueFighter']);

        return new EventResource($event);
    }

    private function applySort($query, string $sort): void
    {
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $query->orderBy($column, $dir);
    }
}

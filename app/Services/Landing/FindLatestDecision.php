<?php

namespace App\Services\Landing;

use App\Models\Fight;

/**
 * The bout the landing page features: the latest event's judges' decision that sits
 * highest on the card (ufcstats lists the main event first, so the lowest bout_order).
 */
final readonly class FindLatestDecision
{
    public function __invoke(): ?Fight
    {
        return Fight::query()
            ->select('fights.*')
            ->join('events', 'events.id', '=', 'fights.event_id')
            ->where('fights.method', 'like', 'Decision%')
            ->orderByDesc('events.date')
            ->orderByDesc('events.id')
            ->orderByRaw('fights.bout_order is null')
            ->orderBy('fights.bout_order')
            ->first();
    }
}

<?php

namespace App\Services\Landing;

use App\Models\Fight;
use Illuminate\Database\Query\Builder;

/**
 * The bout the landing page features: the latest event's judges' decision that sits
 * highest on the card (ufcstats lists the main event first, so the lowest bout_order).
 *
 * Only decisions with complete data count: judges' scorecards, both fighters, and
 * per-round stats for both corners. A half-saved newer decision is skipped.
 */
final readonly class FindLatestDecision
{
    public function __invoke(): ?Fight
    {
        return Fight::query()
            ->select('fights.*')
            ->join('events', 'events.id', '=', 'fights.event_id')
            ->where('fights.method', 'like', 'Decision%')
            ->whereNotNull('fights.red_fighter_id')
            ->whereNotNull('fights.blue_fighter_id')
            ->whereHas('scorecards')
            ->whereExists(fn (Builder $q) => $this->roundsFor($q, 'fights.red_fighter_id'))
            ->whereExists(fn (Builder $q) => $this->roundsFor($q, 'fights.blue_fighter_id'))
            ->orderByDesc('events.date')
            ->orderByDesc('events.id')
            ->orderByRaw('fights.bout_order is null')
            ->orderBy('fights.bout_order')
            ->first();
    }

    /** At least one real round (round 0 is the fight total) for the given corner. */
    private function roundsFor(Builder $query, string $cornerColumn): void
    {
        $query->selectRaw('1')
            ->from('round_stats')
            ->whereColumn('round_stats.fight_id', 'fights.id')
            ->whereColumn('round_stats.fighter_id', $cornerColumn)
            ->where('round_stats.round', '>=', 1);
    }
}

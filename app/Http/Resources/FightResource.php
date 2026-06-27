<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsCorners;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** Full fight payload: corners, result, scorecards, and per-round stats. */
class FightResource extends JsonResource
{
    use PresentsCorners;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ufcstats_id,
            'event' => new EventSummaryResource($this->whenLoaded('event')),
            'bout_order' => $this->bout_order !== null ? (int) $this->bout_order : null,
            'weight_class' => $this->weight_class,
            'is_title_bout' => (bool) $this->is_title_bout,
            'scheduled_rounds' => $this->scheduled_rounds !== null ? (int) $this->scheduled_rounds : null,
            'time_format' => $this->time_format_raw,
            'red' => $this->corner('red'),
            'blue' => $this->corner('blue'),
            'method' => $this->method,
            'method_detail' => $this->method_detail,
            'end_round' => $this->end_round !== null ? (int) $this->end_round : null,
            'end_time' => Format::clock($this->end_time_sec),
            'referee' => $this->referee,
            'scorecards' => ScorecardResource::collection($this->whenLoaded('scorecards')),
            'stats' => $this->when(
                $this->relationLoaded('roundStats'),
                fn () => [
                    'red' => $this->cornerStats($this->red_fighter_id),
                    'blue' => $this->cornerStats($this->blue_fighter_id),
                ],
            ),
        ];
    }

    /**
     * Split a corner's round stats into the fight total (round 0) and per-round rows.
     */
    private function cornerStats(?int $fighterId): array
    {
        /** @var Collection $rounds */
        $rounds = $this->roundStats->where('fighter_id', $fighterId);
        $total = $rounds->firstWhere('round', 0);

        return [
            'total' => $total ? new RoundStatResource($total) : null,
            'rounds' => RoundStatResource::collection(
                $rounds->where('round', '>', 0)->sortBy('round')->values(),
            ),
        ];
    }
}

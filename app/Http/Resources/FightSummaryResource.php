<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsCorners;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A fight without round-by-round stats, for lists and bout histories. */
class FightSummaryResource extends JsonResource
{
    use PresentsCorners;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ufcstats_id,
            'bout_order' => $this->bout_order !== null ? (int) $this->bout_order : null,
            'weight_class' => $this->weight_class,
            'is_title_bout' => (bool) $this->is_title_bout,
            'red' => $this->corner('red'),
            'blue' => $this->corner('blue'),
            'method' => $this->method,
            'method_detail' => $this->method_detail,
            'end_round' => $this->end_round !== null ? (int) $this->end_round : null,
            'end_time' => Format::clock($this->end_time_sec),
            'referee' => $this->referee,
            'event' => new EventSummaryResource($this->whenLoaded('event')),
        ];
    }
}

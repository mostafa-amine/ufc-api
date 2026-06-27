<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ufcstats_id,
            'name' => $this->name,
            'date' => optional($this->date)->toDateString(),
            'location' => [
                'raw' => $this->location_raw,
                'city' => $this->city,
                'state' => $this->state,
                'country' => $this->country,
            ],
            'status' => $this->status,
            'url' => $this->url,
            'fights' => FightSummaryResource::collection($this->whenLoaded('fights')),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ufcstats_id,
            'name' => $this->name,
            'date' => optional($this->date)->toDateString(),
            'location' => $this->location_raw,
            'status' => $this->status,
            'url' => $this->url,
        ];
    }
}

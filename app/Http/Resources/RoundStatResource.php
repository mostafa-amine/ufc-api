<?php

namespace App\Http\Resources;

use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoundStatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'round' => (int) $this->round,
            'knockdowns' => (int) $this->knockdowns,
            'sig_str' => Format::strikes($this->sig_str_landed, $this->sig_str_attempted),
            'total_str' => Format::strikes($this->total_str_landed, $this->total_str_attempted),
            'takedowns' => Format::strikes($this->takedowns_landed, $this->takedowns_attempted),
            'sub_attempts' => (int) $this->sub_attempts,
            'reversals' => (int) $this->reversals,
            'control_time_sec' => $this->control_time_sec !== null ? (int) $this->control_time_sec : null,
            'targets' => [
                'head' => Format::strikes($this->head_landed, $this->head_attempted),
                'body' => Format::strikes($this->body_landed, $this->body_attempted),
                'leg' => Format::strikes($this->leg_landed, $this->leg_attempted),
            ],
            'positions' => [
                'distance' => Format::strikes($this->distance_landed, $this->distance_attempted),
                'clinch' => Format::strikes($this->clinch_landed, $this->clinch_attempted),
                'ground' => Format::strikes($this->ground_landed, $this->ground_attempted),
            ],
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScorecardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'judge' => $this->judge_name,
            'red' => $this->red_score !== null ? (int) $this->red_score : null,
            'blue' => $this->blue_score !== null ? (int) $this->blue_score : null,
        ];
    }
}

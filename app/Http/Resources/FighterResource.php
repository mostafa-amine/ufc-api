<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FighterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ufcstats_id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'physical' => [
                'height_in' => $this->height_in !== null ? (int) $this->height_in : null,
                'weight_lb' => $this->weight_lb !== null ? (int) $this->weight_lb : null,
                'reach_in' => $this->reach_in !== null ? (float) $this->reach_in : null,
                'stance' => $this->stance,
                'dob' => optional($this->dob)->toDateString(),
            ],
            'record' => [
                'wins' => (int) $this->wins,
                'losses' => (int) $this->losses,
                'draws' => (int) $this->draws,
                'no_contests' => (int) $this->no_contests,
            ],
            'stats' => [
                'slpm' => $this->slpm,
                'str_acc' => $this->str_acc,
                'sapm' => $this->sapm,
                'str_def' => $this->str_def,
                'td_avg' => $this->td_avg,
                'td_acc' => $this->td_acc,
                'td_def' => $this->td_def,
                'sub_avg' => $this->sub_avg,
            ],
            'url' => $this->url,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Compact fighter reference for embedding inside fight payloads. */
class FighterRefResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ufcstats_id,
            'name' => $this->name,
            'nickname' => $this->nickname,
        ];
    }
}

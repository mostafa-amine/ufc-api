<?php

namespace App\Http\Resources\Concerns;

use App\Http\Resources\FighterRefResource;

/**
 * Shared corner presentation for fight resources. Expects the consuming resource
 * to wrap a Fight model with redFighter/blueFighter relations loaded.
 */
trait PresentsCorners
{
    /** Per-corner result label from the fight outcome + winner. */
    protected function cornerOutcome(string $corner): ?string
    {
        $fighterId = $corner === 'red' ? $this->red_fighter_id : $this->blue_fighter_id;

        return match ($this->outcome) {
            'draw' => 'draw',
            'nc' => 'nc',
            'pending' => null,
            default => $this->winner_fighter_id !== null
                ? ($this->winner_fighter_id === $fighterId ? 'win' : 'loss')
                : null,
        };
    }

    protected function corner(string $corner): array
    {
        $relation = $corner === 'red' ? 'redFighter' : 'blueFighter';

        return [
            'fighter' => $this->$relation
                ? new FighterRefResource($this->$relation)
                : null,
            'outcome' => $this->cornerOutcome($corner),
        ];
    }
}

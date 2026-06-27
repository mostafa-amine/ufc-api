<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fight extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_title_bout' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'ufcstats_id';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function redFighter(): BelongsTo
    {
        return $this->belongsTo(Fighter::class, 'red_fighter_id');
    }

    public function blueFighter(): BelongsTo
    {
        return $this->belongsTo(Fighter::class, 'blue_fighter_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Fighter::class, 'winner_fighter_id');
    }

    public function roundStats(): HasMany
    {
        return $this->hasMany(RoundStat::class)->orderBy('round');
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(Scorecard::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fighter extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'dob' => 'date',
        'last_scraped_at' => 'datetime',
        'reach_in' => 'float',
        'slpm' => 'float',
        'str_acc' => 'float',
        'sapm' => 'float',
        'str_def' => 'float',
        'td_avg' => 'float',
        'td_acc' => 'float',
        'td_def' => 'float',
        'sub_avg' => 'float',
    ];

    public function getRouteKeyName(): string
    {
        return 'ufcstats_id';
    }

    public function fightsAsRed(): HasMany
    {
        return $this->hasMany(Fight::class, 'red_fighter_id');
    }

    public function fightsAsBlue(): HasMany
    {
        return $this->hasMany(Fight::class, 'blue_fighter_id');
    }

    /**
     * Query for every bout this fighter took part in (either corner).
     */
    public function fights(): Builder
    {
        return Fight::query()
            ->where('red_fighter_id', $this->id)
            ->orWhere('blue_fighter_id', $this->id);
    }
}

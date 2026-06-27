<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'last_scraped_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'ufcstats_id';
    }

    public function fights(): HasMany
    {
        return $this->hasMany(Fight::class)->orderBy('bout_order');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundStat extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function fight(): BelongsTo
    {
        return $this->belongsTo(Fight::class);
    }

    public function fighter(): BelongsTo
    {
        return $this->belongsTo(Fighter::class);
    }
}

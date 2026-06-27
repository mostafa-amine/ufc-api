<?php

use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FightController;
use App\Http\Controllers\Api\V1\FighterController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', [HealthController::class, 'show']);

    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{event}', [EventController::class, 'show']);

    Route::get('fighters', [FighterController::class, 'index']);
    Route::get('fighters/{fighter}', [FighterController::class, 'show']);
    Route::get('fighters/{fighter}/fights', [FighterController::class, 'fights']);

    Route::get('fights', [FightController::class, 'index']);
    Route::get('fights/{fight}', [FightController::class, 'show']);
});

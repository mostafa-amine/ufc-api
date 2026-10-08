<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\ScrapeRun;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /** Check the API status */
    public function show(): JsonResponse
    {
        $lastRun = ScrapeRun::query()->latest('id')->first();

        return response()->json([
            'data' => [
                'status' => 'ok',
                'counts' => [
                    'events' => Event::count(),
                    'fighters' => Fighter::count(),
                    'fights' => Fight::count(),
                ],
                'last_scrape' => $lastRun ? [
                    'type' => $lastRun->type,
                    'status' => $lastRun->status,
                    'finished_at' => optional($lastRun->finished_at)->toIso8601String(),
                ] : null,
            ],
        ]);
    }
}

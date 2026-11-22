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
    /**
     * The update runs once a day; a couple of hours of slack lets a slow run
     * finish before the data counts as stale.
     */
    private const STALE_AFTER_HOURS = 26;

    /**
     * Check the API status
     *
     * `status` is `ok` while the data was updated in the last day, and `stale` once it was not.
     *
     * @response {"data":{"status":"ok","counts":{"events":767,"fighters":4594,"fights":8571},"last_scrape":{"type":"incremental","status":"success","finished_at":"2026-11-22T09:04:12+00:00"},"last_successful_scrape":{"type":"incremental","finished_at":"2026-11-22T09:04:12+00:00"},"latest_event":{"id":"cbf69fa846c7e3ee","name":"UFC Fight Night: Prochazka vs. Stirling","date":"2026-11-21","fights":12,"fights_with_results":12}}}
     */
    public function show(): JsonResponse
    {
        $lastRun = ScrapeRun::query()->latest('id')->first();
        // A partial run still saved new data, so it counts as an update.
        $lastGoodRun = ScrapeRun::query()
            ->whereIn('status', ['success', 'partial'])
            ->whereNotNull('finished_at')
            ->latest('finished_at')
            ->first();

        $fresh = $lastGoodRun && $lastGoodRun->finished_at->gt(now()->subHours(self::STALE_AFTER_HOURS));

        $latestEvent = Event::query()
            ->whereNotNull('date')
            ->where('date', '<=', today())
            ->orderByDesc('date')
            ->withCount([
                'fights',
                'fights as fights_with_results_count' => fn ($q) => $q->where('outcome', '!=', 'pending'),
            ])
            ->first();

        return response()->json([
            'data' => [
                'status' => $fresh ? 'ok' : 'stale',
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
                'last_successful_scrape' => $lastGoodRun ? [
                    'type' => $lastGoodRun->type,
                    'finished_at' => $lastGoodRun->finished_at->toIso8601String(),
                ] : null,
                'latest_event' => $latestEvent ? [
                    'id' => $latestEvent->ufcstats_id,
                    'name' => $latestEvent->name,
                    'date' => $latestEvent->date?->toDateString(),
                    'fights' => $latestEvent->fights_count,
                    'fights_with_results' => $latestEvent->fights_with_results_count,
                ] : null,
            ],
        ]);
    }
}

<?php

namespace App\Providers;

use App\Scraping\PageFetcher;
use App\Scraping\UfcStatsClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PageFetcher::class, fn () => UfcStatsClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Per-key rate limiting, tier-aware. Free tier: 60 req/min.
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $perMinute = match ($user?->rate_tier) {
                'pro' => 1000,
                'unlimited' => 100000,
                default => 60,
            };

            return Limit::perMinute($perMinute)->by($user?->id ?: $request->ip());
        });
    }
}

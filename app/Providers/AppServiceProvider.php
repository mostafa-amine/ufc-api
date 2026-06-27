<?php

namespace App\Providers;

use App\Scraping\PageFetcher;
use App\Scraping\UfcStatsClient;
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
        //
    }
}

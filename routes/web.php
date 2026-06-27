<?php

use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\RoundStat;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing', [
        'counts' => [
            'events' => Event::count(),
            'fighters' => Fighter::count(),
            'fights' => Fight::count(),
            'rounds' => RoundStat::where('round', '>', 0)->count(),
        ],
    ]);
});

Route::get('/docs', fn () => view('docs'));

Route::get('/docs/openapi.yaml', function () {
    $path = storage_path('app/private/scribe/openapi.yaml');
    abort_unless(is_file($path), 404);

    return response()->file($path, ['Content-Type' => 'application/yaml']);
});

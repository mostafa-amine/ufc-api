<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use App\Services\Landing\BuildTaleOfTheTape;
use App\Services\Landing\FindLatestDecision;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    /** Marketing landing page: the latest judges' decision, drawn beside the API response it came from. */
    public function home(FindLatestDecision $findLatestDecision, BuildTaleOfTheTape $buildTape): View
    {
        $fight = $findLatestDecision();

        return view('landing', [
            'tape' => $fight ? $buildTape($fight) : null,
            'counts' => [
                'events' => Event::count(),
                'fights' => Fight::count(),
                'fighters' => Fighter::count(),
            ],
        ]);
    }

    /** Interactive API reference (RapiDoc). */
    public function docs(): View
    {
        return view('docs');
    }

    /** Serve the Scribe-generated OpenAPI spec consumed by the docs page. */
    public function openapi(): Response
    {
        $path = storage_path('app/private/scribe/openapi.yaml');
        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, ['Content-Type' => 'application/yaml']);
    }
}

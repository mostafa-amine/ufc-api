<?php

namespace App\Http\Controllers;

use App\Services\Landing\BuildTaleOfTheTape;
use App\Services\Landing\FindLatestDecision;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    /** Marketing landing page: the tale of the tape of the latest judges' decision. */
    public function home(FindLatestDecision $findLatestDecision, BuildTaleOfTheTape $buildTape): View
    {
        $fight = $findLatestDecision();

        return view('landing', [
            'tape' => $fight ? $buildTape($fight) : null,
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

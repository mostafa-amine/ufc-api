<?php

use Knuckles\Scribe\Extracting\RouteDocBlocker;

it('names every API endpoint exactly as agreed for the docs menu', function () {
    // Scribe turns the first docblock line of each controller method into the summary
    // that the docs menu shows, so these lines are the menu names.
    $names = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'v1/'))
        ->mapWithKeys(fn ($route) => [
            $route->methods()[0].' '.$route->uri() => trim(RouteDocBlocker::getDocBlocksFromRoute($route)['method']->getShortDescription()),
        ])
        ->sortKeys()
        ->all();

    expect($names)->toBe([
        'GET v1/events' => 'List events',
        'GET v1/events/{event}' => 'Get an event',
        'GET v1/fighters' => 'List fighters',
        'GET v1/fighters/{fighter}' => 'Get a fighter',
        'GET v1/fighters/{fighter}/fights' => "Get a fighter's fights",
        'GET v1/fights' => 'List fights',
        'GET v1/fights/{fight}' => 'Get a fight',
        'GET v1/health' => 'Check the API status',
        'POST v1/register' => 'Register for an API key',
    ]);
});

it('keeps the register sentence as the description, not the name', function () {
    $route = collect(app('router')->getRoutes())->first(fn ($route) => $route->uri() === 'v1/register');
    $doc = RouteDocBlocker::getDocBlocksFromRoute($route)['method'];

    expect(trim($doc->getShortDescription()))->toBe('Register for an API key')
        ->and(trim($doc->getLongDescription()->getContents()))->toBe('Register and receive a free API key. Send it as `Authorization: Bearer <key>`.');
});

<?php

use Knuckles\Scribe\Extracting\RouteDocBlocker;

it('opens in the light theme with no theme switch', function () {
    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('theme="light"')
        ->toContain('bg-color="#F6F5F2"')
        ->not->toContain('theme="dark"')
        ->not->toContain('prefers-color-scheme')
        ->not->toMatch('/allow-theme|theme-switch|data-theme/');
});

it('uses the landing page fonts and red and blue', function () {
    $html = $this->get('/docs')->getContent();

    expect($html)->toContain('family=Archivo+Narrow')
        ->toContain('regular-font="\'Archivo\'')
        ->toContain('mono-font="\'IBM Plex Mono\'')
        ->toContain('primary-color="#C41E26"')
        ->toContain('#1F4FD1')
        ->not->toContain('Sora')
        ->not->toContain('JetBrains Mono');
});

it('shows the same top nav as the landing page', function () {
    $nav = fn (string $url) => preg_match('#<nav class="site-nav">.*?</nav>#s', $this->get($url)->getContent(), $m) ? $m[0] : null;

    expect($nav('/docs'))->not->toBeNull()
        ->toContain('unofficial · open source')
        ->toContain('Get a free key')
        ->toBe($nav('/'));
});

it('keeps the docs content and the try-it feature as they are', function () {
    $html = $this->get('/docs')->getContent();

    expect($html)->toContain('spec-url="/docs/openapi.yaml"')
        ->toContain('render-style="read"')
        ->toContain('allow-authentication="true"')
        ->toContain('allow-server-selection="true"')
        ->toContain('fill-request-fields-with-example="true"')
        ->not->toContain('allow-try="false"')
        ->toContain('Get a free key from <code');
});

it('sets all reading text in Archivo at one 16px size', function () {
    $html = $this->get('/docs')->getContent();

    // RapiDoc sizes every description, label and table cell from these three variables.
    expect($html)->toContain('--font-size-regular:16px')
        ->toContain('--font-size-small:16px')
        ->toContain('regular-font="\'Archivo\'');

    preg_match('#<div slot="auth" style="([^"]*)"#', $html, $slot);
    expect($slot[1] ?? '')->toContain("font-family:'Archivo'")->toContain('font-size:16px');
});

it('keeps the code font for code only', function () {
    $html = $this->get('/docs')->getContent();

    expect($html)->toContain('mono-font="\'IBM Plex Mono\'')
        ->toContain('--font-size-mono:15px');

    // Outside RapiDoc's own code, only the inline <code> sample uses the code font.
    preg_match_all('#<(?!code)[a-z]+[^>]*style="[^"]*Plex Mono#', $html, $mono);
    expect($mono[0])->toBe([]);
});

it('links the OpenAPI file next to the intro, not in the top nav', function () {
    $html = $this->get('/docs')->getContent();

    // RapiDoc places the "overview" slot right under the API title, before the intro text.
    expect($html)->toMatch('#<div slot="overview"[^>]*>\s*<a href="/docs/openapi.yaml"[^>]*>OpenAPI file</a>#');

    preg_match('#<nav class="site-nav">.*?</nav>#s', $html, $nav);
    expect($nav[0])->not->toContain('openapi.yaml');
});

it('serves the OpenAPI file the link points to', function () {
    // A throwaway storage folder, so the test never touches the real generated spec.
    $storage = sys_get_temp_dir().'/docs-openapi-'.uniqid();
    mkdir($storage.'/app/private/scribe', 0777, true);
    $spec = "openapi: 3.0.3\ninfo:\n  title: 'Spec served by the docs link'\n";
    file_put_contents($storage.'/app/private/scribe/openapi.yaml', $spec);
    $realStorage = storage_path();
    $this->app->useStoragePath($storage);

    try {
        $response = $this->get('/docs/openapi.yaml');

        $response->assertOk()->assertHeader('Content-Type', 'application/yaml');
        expect($response->getContent())->toBe($spec);
    } finally {
        $this->app->useStoragePath($realStorage);
        unlink($storage.'/app/private/scribe/openapi.yaml');
        rmdir($storage.'/app/private/scribe');
        rmdir($storage.'/app/private');
        rmdir($storage.'/app');
        rmdir($storage);
    }
});

it('shows the selected server label in Archivo and only the URL in the code font', function () {
    $html = $this->get('/docs')->getContent();

    // RapiDoc prints "SELECTED: <url>" as one text node in the code font, so its line is hidden...
    expect($html)->toMatch('#rapi-doc::part\(label-selected-server\)\{display:none\}#');

    // ...and redrawn in the "servers" slot: label in Archivo 16px, URL alone in <code>.
    expect($html)->toMatch('#<div slot="servers" id="selected-server" style="[^"]*font-family:\'Archivo\'[^"]*font-size:16px[^"]*">SELECTED: <code[^>]*>[^<]*</code></div>#');

    // The URL follows RapiDoc's choice: set once the spec loads and on every server change.
    expect($html)->toContain("addEventListener('spec-loaded'")
        ->toContain("addEventListener('api-server-change'");
});

it('lists endpoints by name in the left menu, not by path', function () {
    expect($this->get('/docs')->getContent())->toContain('use-path-in-nav-bar="false"')
        ->not->toContain('use-path-in-nav-bar="true"');
});

it('gives every API endpoint a name for the menu', function () {
    // The served spec is generated by Scribe at deploy time and isn't in git, so this reads
    // what Scribe builds each operation's summary from: the first line of the method's docblock.
    $routes = collect(app('router')->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'v1/'));
    expect($routes)->not->toBeEmpty();

    $names = $routes->mapWithKeys(fn ($route) => [
        implode('|', $route->methods()).' '.$route->uri() => trim(
            RouteDocBlocker::getDocBlocksFromRoute($route)['method']->getShortDescription()
        ),
    ]);

    expect($names->filter(fn ($name) => $name === '')->keys()->all())->toBe([])
        ->and($names->filter(fn ($name) => str_contains($name, '/v1/'))->keys()->all())->toBe([])
        // Short enough to sit on one line of the 380px menu.
        ->and($names->filter(fn ($name) => mb_strlen($name) > 30)->keys()->all())->toBe([]);

    expect($names['POST v1/register'])->toBe('Register for an API key');
});

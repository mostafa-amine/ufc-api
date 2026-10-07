<?php

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

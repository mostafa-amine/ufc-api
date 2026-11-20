<?php

it('shows the no-gambling rule on the terms page', function () {
    $this->get('/terms')->assertOk()
        ->assertSee('Not for gambling.')
        ->assertSeeInOrder(['Sportsbooks', 'odds', 'Betting tips', 'prediction markets', 'Fantasy contests'])
        ->assertSee('We may refuse a key, or switch one off');
});

it('links the terms from the landing page and the docs', function () {
    $this->get('/')->assertSee('href="/terms"', false)->assertSee('Not for gambling');
    $this->get('/docs')->assertSee('href="/terms"', false)->assertSee('no gambling of any kind');
});

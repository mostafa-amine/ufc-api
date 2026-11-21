<?php

use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('calls from a browser on another website', function () {
    it('answers the browser pre-check for a key-protected endpoint', function () {
        $this->call('OPTIONS', '/v1/fighters', server: [
            'HTTP_ORIGIN' => 'https://my-fight-app.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Max-Age', '86400');
    });

    it('lets the browser read the answer and the rate-limit headers', function () {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/v1/events', ['Origin' => 'https://my-fight-app.example'])->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
        expect($response->headers->get('Access-Control-Expose-Headers'))->toContain('X-RateLimit-Remaining')->toContain('ETag');
    });

    it('also allows it on errors, so the page can show why a call failed', function () {
        $this->getJson('/v1/events', ['Origin' => 'https://my-fight-app.example'])
            ->assertUnauthorized()->assertHeader('Access-Control-Allow-Origin', '*');
    });

    it('leaves the website pages alone', function () {
        expect($this->get('/docs', ['Origin' => 'https://my-fight-app.example'])->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    });
});

describe('reusing an answer that has not changed', function () {
    beforeEach(fn () => Sanctum::actingAs(User::factory()->create()));

    it('marks a good answer as reusable for 5 minutes with a fingerprint', function () {
        Event::factory()->create();

        $response = $this->getJson('/v1/events')->assertOk();
        expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('max-age=300')
            ->and($response->headers->get('ETag'))->not->toBeEmpty()
            ->and($response->getVary())->toContain('Authorization');
    });

    it('sends an empty "not changed" reply when the fingerprint still matches', function () {
        Event::factory()->create();
        $etag = $this->getJson('/v1/events')->headers->get('ETag');

        $again = $this->getJson('/v1/events', ['If-None-Match' => $etag]);
        $again->assertStatus(304);
        expect($again->getContent())->toBe('');
    });

    it('sends the full answer again once the data changed', function () {
        Event::factory()->create();
        $etag = $this->getJson('/v1/events')->headers->get('ETag');
        Event::factory()->create();

        $this->getJson('/v1/events', ['If-None-Match' => $etag])->assertOk()->assertJsonCount(2, 'data');
    });

    it('never marks errors as reusable', function () {
        $response = $this->getJson('/v1/events/0000000000000000')->assertNotFound();
        expect($response->headers->has('ETag'))->toBeFalse()
            ->and($response->headers->get('Cache-Control'))->not->toContain('max-age');
    });

    it('keeps the public status check reusable for only a minute', function () {
        expect($this->getJson('/v1/health')->assertOk()->headers->get('Cache-Control'))->toContain('max-age=60');
    });
});

it('caps how many fights of one fighter come back per page', function () {
    Sanctum::actingAs(User::factory()->create());
    $fighter = Fighter::factory()->create();

    $this->getJson("/v1/fighters/{$fighter->ufcstats_id}/fights?per_page=5000")->assertUnprocessable();
    $this->getJson("/v1/fighters/{$fighter->ufcstats_id}/fights?per_page=100")->assertOk();
});

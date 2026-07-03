<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('issues a free API key on registration', function () {
    $res = $this->postJson('/v1/register', [
        'name' => 'Test Dev',
        'email' => 'dev@example.com',
        'password' => 'password123',
    ])->assertCreated();

    expect($res->json('data.api_key'))->toBeString()->not->toBeEmpty()
        ->and($res->json('data.rate_tier'))->toBe('free');

    // The issued key actually authenticates.
    $this->withToken($res->json('data.api_key'))
        ->getJson('/v1/fighters')
        ->assertOk();
});

it('rejects duplicate registration with the error envelope', function () {
    User::factory()->create(['email' => 'dupe@example.com']);

    $this->postJson('/v1/register', [
        'name' => 'X', 'email' => 'dupe@example.com', 'password' => 'password123',
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['email']]]);
});

it('blocks unauthenticated access to data endpoints', function () {
    $this->getJson('/v1/fighters')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('returns a JSON 401 even when the client omits the Accept header', function () {
    // A bare request (no Accept: application/json) previously fell through to the
    // non-existent `login` route and 500'd. ForceJsonResponse keeps it a clean 401.
    $this->get('/v1/fighters')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('allows public access to health without a key', function () {
    $this->getJson('/v1/health')->assertOk();
});

it('returns the error envelope for unknown resources', function () {
    \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());

    $this->getJson('/v1/fights/ffffffffffffffff')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});

it('throttles repeated registration attempts', function () {
    $payload = fn ($i) => [
        'name' => 'X', 'email' => "spam{$i}@example.com", 'password' => 'password123',
    ];

    // register route is throttled to 6/min by IP.
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/v1/register', $payload($i))->assertCreated();
    }

    $this->postJson('/v1/register', $payload(99))
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');
});

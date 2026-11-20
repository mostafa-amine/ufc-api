<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('signing up', function () {
    it('makes an account, logs it in and opens the dashboard', function () {
        $this->post('/register', [
            'email' => 'jane@example.com', 'password' => 'supersecret', 'password_confirmation' => 'supersecret', 'terms' => '1',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'jane@example.com')->first();
        expect($user->name)->toBe('jane')->and($user->rate_tier)->toBe('free');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk()->assertSee('Logged in as')->assertSee('jane@example.com')->assertSee('No keys yet');
    });

    it('needs the terms ticked', function () {
        $this->post('/register', ['email' => 'jane@example.com', 'password' => 'supersecret', 'password_confirmation' => 'supersecret'])
            ->assertSessionHasErrors(['terms' => 'Please agree to the terms to make an account.']);
        expect(User::count())->toBe(0);
    });

    it('refuses a short or mistyped password and a taken email', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', ['email' => 'new@example.com', 'password' => 'short', 'password_confirmation' => 'short', 'terms' => '1'])
            ->assertSessionHasErrors('password');
        $this->post('/register', ['email' => 'new@example.com', 'password' => 'supersecret', 'password_confirmation' => 'different', 'terms' => '1'])
            ->assertSessionHasErrors('password');
        $this->post('/register', ['email' => 'taken@example.com', 'password' => 'supersecret', 'password_confirmation' => 'supersecret', 'terms' => '1'])
            ->assertSessionHasErrors(['email' => 'An account with this email already exists. Log in instead.']);
        expect(User::count())->toBe(1);
    });
});

describe('logging in', function () {
    beforeEach(fn () => $this->user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'supersecret']));

    it('logs in with the right password', function () {
        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'supersecret'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
    });

    it('works for accounts made through the API sign-up', function () {
        $this->postJson('/v1/register', ['name' => 'Dev', 'email' => 'api@example.com', 'password' => 'password123'])->assertCreated();
        $this->app['auth']->forgetGuards();

        $this->post('/login', ['email' => 'api@example.com', 'password' => 'password123'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertSee('api-key');
    });

    it('refuses a wrong password, then makes you wait after five tries', function () {
        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'jane@example.com', 'password' => 'wrong'])
                ->assertSessionHasErrors(['email' => 'That email and password don\'t match an account.']);
        }
        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'supersecret'])->assertSessionHasErrors('email');
        expect(session('errors')->first('email'))->toStartWith('Too many tries.');
        $this->assertGuest();
    });

    it('refuses a blocked account', function () {
        $this->user->forceFill(['blocked_at' => now()])->save();

        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'supersecret'])
            ->assertSessionHasErrors(['email' => 'This account was blocked for breaking the terms of use.']);
        $this->assertGuest();
    });

    it('logs out', function () {
        $this->actingAs($this->user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    });

    it('sends guests to the login page and logged-in people to the dashboard', function () {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->actingAs($this->user)->get('/login')->assertRedirect('/dashboard');
        $this->get('/register')->assertRedirect('/dashboard');
    });

    it('still answers API guests with the JSON error, not a redirect', function () {
        $this->getJson('/v1/fighters')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
        $this->get('/v1/fighters')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    });
});

describe('the keys on the dashboard', function () {
    beforeEach(fn () => $this->user = User::factory()->create());

    it('makes a key, shows it once, and the key works on the API', function () {
        $this->actingAs($this->user)->post('/dashboard/keys', ['name' => 'my app'])->assertRedirect('/dashboard');

        $page = $this->get('/dashboard')->assertSee('Your new key “my app”')->assertSee('You won\'t be able to see it again.', false);
        $value = trim(preg_match('#id="new-key-value">([^<]+)<#', $page->getContent(), $m) ? $m[1] : '');
        expect($value)->toContain('|');

        $this->get('/dashboard')->assertDontSee($value)->assertSee('my app')->assertSee('Never used');

        $this->app['auth']->forgetGuards();
        $this->withToken($value)->getJson('/v1/fighters')->assertOk();
    });

    it('needs a name', function () {
        $this->actingAs($this->user)->post('/dashboard/keys', ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Give the key a name, like "my app".']);
        expect($this->user->tokens()->count())->toBe(0);
    });

    it('stops at the most keys allowed', function () {
        foreach (range(1, User::MAX_KEYS) as $i) {
            $this->user->createToken("key {$i}");
        }

        $this->actingAs($this->user)->post('/dashboard/keys', ['name' => 'one more'])->assertSessionHasErrors('name');
        expect($this->user->tokens()->count())->toBe(User::MAX_KEYS);
    });

    it('deletes a key, and the deleted key stops working', function () {
        $token = $this->user->createToken('old');

        $this->actingAs($this->user)->delete('/dashboard/keys/'.$token->accessToken->id)->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertSee('Key deleted.')->assertDontSee('>old<', false);

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/v1/fighters')->assertUnauthorized();
    });

    it('cannot delete someone else\'s key', function () {
        $theirs = User::factory()->create()->createToken('theirs');

        $this->actingAs($this->user)->delete('/dashboard/keys/'.$theirs->accessToken->id)->assertNotFound();
        expect($theirs->accessToken->fresh())->not->toBeNull();
    });

    it('lets a blocked account neither make keys nor use one left behind', function () {
        $left = $this->user->createToken('left behind');
        $this->user->forceFill(['blocked_at' => now()])->save();

        $this->actingAs($this->user)->post('/dashboard/keys', ['name' => 'new'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken($left->plainTextToken)->getJson('/v1/fighters')->assertUnauthorized();
    });
});

it('the switch-off command also blocks the account from logging in', function () {
    User::factory()->create(['email' => 'odds@example.com', 'password' => 'supersecret']);

    $this->artisan('ufc:revoke-key', ['email' => 'odds@example.com'])->assertSuccessful();

    $this->post('/login', ['email' => 'odds@example.com', 'password' => 'supersecret'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BroadcastAuthTest extends TestCase
{
    public function test_presence_channel_authorizes_with_id_and_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-online',
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk();

        // Pusher's protocol encodes `channel_data` as a JSON *string* nested inside
        // the outer JSON response body (see vendor/pusher/pusher-php-server's
        // Pusher::authorizeChannel()), not as a nested JSON object. assertJson()'s
        // array-subset comparison can't see through an encoded string, so decode it
        // ourselves before asserting on its shape.
        $encodedChannelData = $response->json('channel_data');
        $this->assertIsString($encodedChannelData);

        $channelData = json_decode($encodedChannelData, true);

        $this->assertSame([
            'user_id' => $user->id,
            'user_info' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
        ], $channelData);
    }

    public function test_api_guard_authorizes_own_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$user->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk();
    }

    public function test_api_guard_rejects_other_users_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$other->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertForbidden();
    }

    public function test_web_guard_authorizes_own_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$user->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk();
    }

    public function test_web_guard_rejects_other_users_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$other->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertForbidden();
    }

    /**
     * actingAs() only sets the user directly on the resolved guard instance in the
     * container — it never runs the 'web' middleware group (session start, cookie
     * decryption), so it can't catch a bug where the broadcasting/auth route is
     * missing the 'web' middleware.
     *
     * A naive fix — issue a real login request, capture the session cookie, replay
     * it on a second request within the same test — is ALSO a false positive: Laravel's
     * AuthManager caches guard instances per container (guard()'s `$this->guards[$name]
     * ??= ...`), and SessionGuard::user() short-circuits on an already-set `$user`
     * property. Since `auth()->guard('web')->login($user)` sets that property directly
     * on the cached guard object, any later call to Auth::guard('web') within the same
     * test returns the user immediately, without ever touching the session — regardless
     * of whether the second request's middleware stack included 'web' at all. The
     * container's 'session.store' binding is also a singleton with an in-memory `Store`
     * object whose attributes survive `save()`, compounding the leak.
     *
     * The only way to get genuine two-request isolation in-process is to rebuild the
     * whole container between the two calls via refreshApplication() (fresh AuthManager,
     * fresh SessionManager, fresh guards — nothing cached carries over). This app uses
     * DatabaseMigrations (not RefreshDatabase), so there's no open transaction to lose,
     * and the test DB is a real MySQL instance, so the user row committed by the first
     * app instance is visible to the second. The 'array' session driver's backing store
     * is a PHP array tied to the handler object and would not survive that rebuild, so
     * this test switches to the 'file' driver (which persists to
     * storage/framework/sessions) for the duration of the test.
     *
     * Also note: postJson()/getJson() do NOT send cookies by default (Laravel's test
     * client mimics XHR "credentials: omit" semantics for JSON requests) — withCredentials()
     * must be called explicitly, otherwise the replayed session cookie is silently dropped
     * and the request looks unauthenticated regardless of what the route's middleware does.
     */
    public function test_web_guard_authorizes_own_channel_via_isolated_session_cookie_request(): void
    {
        $previousSessionDriverRaw = $_ENV['SESSION_DRIVER'] ?? $_SERVER['SESSION_DRIVER'] ?? null;
        $previousSessionDriver = is_string($previousSessionDriverRaw) ? $previousSessionDriverRaw : null;

        $this->overrideSessionDriverForBothAppInstances('file');
        $this->withCredentials();

        try {
            $user = User::factory()->create();

            Route::middleware('web')->get('/__test-support/session-login/{user}', function (User $user) {
                auth()->guard('web')->login($user);

                return response()->noContent();
            });

            $loginResponse = $this->get('/__test-support/session-login/'.$user->id);
            $loginResponse->assertNoContent();

            $configuredCookieName = config('session.cookie');
            $this->assertIsString($configuredCookieName, 'session.cookie config must be a string.');
            $cookieName = $configuredCookieName;

            $sessionCookie = $loginResponse->getCookie($cookieName);

            $this->assertNotNull($sessionCookie, 'Login response did not set a session cookie.');

            $cookieValue = $sessionCookie->getValue();

            $this->assertIsString($cookieValue, 'Login response session cookie had no value.');

            // Rebuild the entire container: fresh AuthManager, fresh SessionManager,
            // fresh guards, fresh route registration (BroadcastServiceProvider::boot()
            // runs again). Nothing from the first request's guard/session objects
            // carries over — only what was actually persisted to disk does.
            $this->refreshApplication();
            $this->overrideSessionDriverForBothAppInstances('file');
            $this->withCredentials();

            $response = $this->withCookie($cookieName, $cookieValue)
                ->postJson('/broadcasting/auth', [
                    'channel_name' => 'private-App.Models.User.'.$user->id,
                    'socket_id' => '1234.1234',
                ]);

            $response->assertOk();
        } finally {
            if ($previousSessionDriver === null) {
                putenv('SESSION_DRIVER');
                unset($_ENV['SESSION_DRIVER'], $_SERVER['SESSION_DRIVER']);
            } else {
                putenv('SESSION_DRIVER='.$previousSessionDriver);
                $_ENV['SESSION_DRIVER'] = $previousSessionDriver;
                $_SERVER['SESSION_DRIVER'] = $previousSessionDriver;
            }
        }
    }

    /**
     * Overrides the session driver both on the currently booted application's config
     * (for immediate effect) and via real environment variables (so a subsequent
     * refreshApplication() picks it up too, since a freshly booted app reads env(),
     * not the previous app's mutated config repository).
     */
    private function overrideSessionDriverForBothAppInstances(string $driver): void
    {
        putenv('SESSION_DRIVER='.$driver);
        $_ENV['SESSION_DRIVER'] = $driver;
        $_SERVER['SESSION_DRIVER'] = $driver;

        $this->app['config']->set('session.driver', $driver);
    }
}

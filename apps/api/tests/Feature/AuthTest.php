<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only starts a session for requests from a first-party origin, as the browser SPA is.
        $this->withHeader('Referer', config('app.url'));
    }

    public function test_guests_cannot_access_protected_endpoints(): void
    {
        foreach (['/api/v1/dashboard', '/api/v1/tasks', '/api/v1/goals', '/api/v1/quotes/random', '/api/v1/auth/me'] as $uri) {
            $this->getJson($uri)->assertUnauthorized();
        }
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-9']);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Correct-Horse-9'])
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($user);

        $this->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_me_describes_a_session_that_is_not_remembered(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-9']);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Correct-Horse-9'])
            ->assertJsonPath('meta.session.remembered', false);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('meta.session.remembered', false)
            ->assertJsonPath('meta.session.idle_timeout_minutes', config('session.lifetime'))
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_keep_me_signed_in_sets_a_long_lived_http_only_cookie_and_reports_its_expiry(): void
    {
        Carbon::setTestNow('2026-10-28 10:00:00');
        $user = User::factory()->create(['password' => 'Correct-Horse-9']);

        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Correct-Horse-9', 'remember' => true])
            ->assertOk()
            ->assertJsonPath('meta.session.remembered', true)
            ->assertJsonPath('meta.session.remember_days', 30)
            ->assertJsonPath('meta.session.expires_at', '2026-11-27T10:00:00+00:00');

        $cookie = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($cookie, 'remember cookie set');
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame(Carbon::parse('2026-11-27 10:00:00')->timestamp, $cookie->getExpiresTime());
    }

    public function test_an_expired_session_is_restored_from_the_remember_cookie_and_renewed(): void
    {
        $user = User::factory()->create();
        $user->setRememberToken('remember-token-123');
        $user->save();
        $recaller = Auth::guard('web')->getRecallerName();
        Carbon::setTestNow('2026-12-01 09:00:00');

        $value = $user->id.'|remember-token-123|'.Auth::guard('web')->hashPasswordForCookie($user->getAuthPassword());

        $response = $this->withCredentials()->withCookie($recaller, $value)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('meta.session.remembered', true)
            ->assertJsonPath('meta.session.expires_at', '2026-12-31T09:00:00+00:00');

        $renewed = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === $recaller);
        $this->assertNotNull($renewed, 'remember cookie reissued');
        $this->assertSame(Carbon::parse('2026-12-31 09:00:00')->timestamp, $renewed->getExpiresTime());
    }

    public function test_logout_clears_the_remember_cookie_and_rotates_the_token(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-9']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Correct-Horse-9', 'remember' => true]);
        $token = $user->fresh()->getRememberToken();
        $recaller = Auth::guard('web')->getRecallerName();
        $value = $user->id.'|'.$token.'|'.Auth::guard('web')->hashPasswordForCookie($user->getAuthPassword());

        // Test requests don't keep cookies between calls, so the browser's remember cookie is sent explicitly.
        $response = $this->withCredentials()->withCookie($recaller, $value)->postJson('/api/v1/auth/logout')->assertNoContent();

        $cleared = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($cleared);
        $this->assertTrue($cleared->isCleared());
        $this->assertNotSame($token, $user->fresh()->getRememberToken());
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'nope']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertTooManyRequests();
    }

    public function test_login_without_a_first_party_origin_is_refused_cleanly(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-9']);

        $this->withHeaders(['Referer' => 'https://evil.example'])
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Correct-Horse-9'])
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->getJson('/api/v1/dashboard')
            ->assertUnauthorized()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin');
    }
}

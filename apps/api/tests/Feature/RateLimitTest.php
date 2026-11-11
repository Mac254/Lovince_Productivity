<?php

namespace Tests\Feature;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_custom_throttle_counts_on_its_own(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->getJson('/api/v1/backup')->assertOk();
        }
        $this->actingAs($user)->getJson('/api/v1/backup')->assertTooManyRequests();

        $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'wrong',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertUnprocessable();
    }

    public function test_every_custom_throttle_has_a_unique_prefix(): void
    {
        $prefixes = collect(Route::getRoutes()->getRoutes())
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && preg_match('/^throttle:\d+,\d+/', $middleware))
            ->map(fn (string $middleware) => explode(',', $middleware)[2] ?? null);

        $this->assertNotEmpty($prefixes);
        $this->assertNotContains(null, $prefixes->all(), 'A numeric throttle needs a prefix, or it shares the per-user counter.');
        $this->assertSame($prefixes->count(), $prefixes->unique()->count());
    }
}

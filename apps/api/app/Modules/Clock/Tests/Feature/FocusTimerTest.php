<?php

namespace App\Modules\Clock\Tests\Feature;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FocusTimerTest extends TestCase
{
    use RefreshDatabase;

    private function state(array $overrides = []): array
    {
        return array_merge([
            'preset_id' => 'focus',
            'kind' => 'focus',
            'status' => 'running',
            'duration_ms' => 1_500_000,
            'ends_at' => now()->addMinutes(25)->getTimestampMs(),
            'paused_remaining_ms' => null,
            'started_at' => now()->getTimestampMs(),
            'task' => null,
        ], $overrides);
    }

    public function test_the_timer_is_shared_between_devices(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/focus-timer')->assertOk()->assertJsonPath('data.state', null)->assertJsonPath('data.revision', 0);

        $this->putJson('/api/v1/focus-timer', ['revision' => 0, 'state' => $this->state()])
            ->assertOk()
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.state.status', 'running');

        $this->putJson('/api/v1/focus-timer', ['revision' => 1, 'state' => $this->state(['status' => 'paused', 'ends_at' => null, 'paused_remaining_ms' => 600_000])])
            ->assertOk()
            ->assertJsonPath('data.revision', 2);

        $this->getJson('/api/v1/focus-timer')->assertJsonPath('data.state.status', 'paused')->assertJsonPath('data.revision', 2);
    }

    public function test_an_outdated_device_gets_the_newer_state_back(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson('/api/v1/focus-timer', ['revision' => 0, 'state' => $this->state()]);
        $this->putJson('/api/v1/focus-timer', ['revision' => 1, 'state' => $this->state(['status' => 'idle'])]);

        $this->putJson('/api/v1/focus-timer', ['revision' => 1, 'state' => $this->state(['status' => 'done'])])
            ->assertConflict()
            ->assertJsonPath('data.state.status', 'idle')
            ->assertJsonPath('data.revision', 2);
    }

    public function test_rejects_bad_state_and_guests(): void
    {
        $this->putJson('/api/v1/focus-timer', ['revision' => 0, 'state' => $this->state()])->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->putJson('/api/v1/focus-timer', ['revision' => 0, 'state' => $this->state(['status' => 'flying'])])
            ->assertJsonValidationErrors('state.status');
    }
}

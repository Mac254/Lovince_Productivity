<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requests_are_logged_without_bodies(): void
    {
        Log::spy();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/tasks', ['title' => 'Secret project name'])->assertCreated();

        Log::shouldHaveReceived('log')
            ->withArgs(fn ($level, $message, $context) => $level === 'info'
                && $message === 'POST /api/v1/tasks 201'
                && $context['user_id'] === $user->id
                && ! str_contains(json_encode($context), 'Secret'))
            ->once();
    }

    public function test_task_changes_log_fields_and_status_moves_but_not_content(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['title' => 'Private title']);
        Log::spy();

        $this->actingAs($user)->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed', 'title' => 'Another private title']);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context) => $message === 'task.updated'
                && $context['status'] === 'not_started -> completed'
                && str_contains($context['changed'], 'title')
                && ! str_contains(json_encode($context), 'private'))
            ->once();
    }

    public function test_failed_logins_are_logged_without_the_password(): void
    {
        Log::spy();
        $user = User::factory()->create();

        $this->withHeader('Referer', config('app.url'))
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong-password-123']);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context) => $message === 'auth.failed'
                && $context['email'] === $user->email
                && ! str_contains(json_encode($context), 'wrong-password'))
            ->once();
    }
}

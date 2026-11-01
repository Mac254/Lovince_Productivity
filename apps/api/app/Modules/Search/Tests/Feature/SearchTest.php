<?php

namespace App\Modules\Search\Tests\Feature;

use App\Modules\Blockers\Models\Blocker;
use App\Modules\Goals\Models\Goal;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_your_records_of_every_kind(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->for($user)->create(['title' => 'Send the Invoice', 'description' => null]);
        Goal::factory()->for($user)->create(['title' => 'Grow', 'description' => 'Invoice every client on time']);
        Blocker::factory()->for($user)->for($task)->create(['description' => 'Waiting on invoice details']);
        $user->journalEntries()->create(['date' => '2026-10-30', 'body' => "Long day.\nFinally sent the invoice."]);
        Task::factory()->create(['title' => 'Invoice for someone else']);

        $this->getJson('/api/v1/search?q=invoice')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.kind', 'task')
            ->assertJsonPath('data.0.title', 'Send the Invoice')
            ->assertJsonPath('data.1.kind', 'goal')
            ->assertJsonPath('data.1.excerpt', 'Invoice every client on time')
            ->assertJsonPath('data.2.kind', 'blocker')
            ->assertJsonPath('data.2.task_id', $task->id)
            ->assertJsonPath('data.3.kind', 'journal')
            ->assertJsonPath('data.3.date', '2026-10-30');
    }

    public function test_wildcards_are_matched_literally(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Task::factory()->for($user)->create(['title' => 'Plan 100% of it']);
        Task::factory()->for($user)->create(['title' => 'Plan 1000 of it']);

        $this->getJson('/api/v1/search?q='.urlencode('100%'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Plan 100% of it');
    }

    public function test_the_journal_can_be_left_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->journalEntries()->create(['date' => '2026-10-30', 'body' => 'A private note']);

        $this->patchJson('/api/v1/settings', ['journal' => ['searchable' => false]])->assertOk();

        $this->getJson('/api/v1/search?q=private')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_query_is_validated_and_guests_are_refused(): void
    {
        $this->getJson('/api/v1/search?q=a')->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/search?q=a')->assertUnprocessable()->assertJsonValidationErrors('q');
    }
}

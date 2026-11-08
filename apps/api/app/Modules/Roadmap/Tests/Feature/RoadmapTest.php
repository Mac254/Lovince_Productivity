<?php

namespace App\Modules\Roadmap\Tests\Feature;

use App\Modules\Goals\Models\Goal;
use App\Modules\Roadmap\Models\RoadmapItem;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RoadmapTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_new_items_join_the_end_of_their_stage(): void
    {
        $this->postJson('/api/v1/roadmap-items', ['title' => '  Shared lists  ', 'area' => ' Tasks '])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Shared lists')
            ->assertJsonPath('data.area', 'Tasks')
            ->assertJsonPath('data.stage', 'next')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.done_on', null);

        $this->postJson('/api/v1/roadmap-items', ['title' => 'Export to PDF'])->assertJsonPath('data.position', 2);
        $this->postJson('/api/v1/roadmap-items', ['title' => 'Mobile app', 'stage' => 'later'])->assertJsonPath('data.position', 1);
    }

    public function test_shipping_dates_an_item_and_moving_it_back_clears_the_date(): void
    {
        $item = RoadmapItem::factory()->for($this->user)->create(['stage' => 'now']);

        $this->patchJson("/api/v1/roadmap-items/{$item->id}", ['stage' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'done')
            ->assertJsonPath('data.done_on', today()->toDateString());

        $this->patchJson("/api/v1/roadmap-items/{$item->id}", ['stage' => 'now'])
            ->assertJsonPath('data.done_on', null);

        $this->patchJson("/api/v1/roadmap-items/{$item->id}", ['stage' => 'done', 'done_on' => '2026-11-02'])
            ->assertJsonPath('data.done_on', '2026-11-02');
    }

    public function test_a_phase_follows_its_goals_tasks_due_within_its_dates(): void
    {
        $goal = Goal::factory()->for($this->user)->create(['title' => 'Money roadmap']);
        $make = fn (string $title, string $due, string $status = 'not_started') => Task::factory()->for($this->user)->create(['goal_id' => $goal->id, 'title' => $title, 'due_date' => $due, 'status' => $status]);
        $make('Clear Fuliza', '2026-11-30', 'completed');
        $make('Repay mum', '2027-02-28');
        $make('KES 28,000', '2027-02-28');
        $make('KES 75,000', '2027-07-31');
        $make('Cancelled', '2027-01-10', 'cancelled');
        Task::factory()->for($this->user)->create(['goal_id' => $goal->id, 'title' => 'Payday', 'due_date' => '2026-12-01', 'recurrence' => 'monthly']);
        Task::factory()->for($this->user)->create(['title' => 'Other goal', 'due_date' => '2027-01-01']);

        $id = $this->postJson('/api/v1/roadmap-items', [
            'title' => 'Build the habit', 'goal_id' => $goal->id, 'starts_on' => '2026-11-01', 'ends_on' => '2027-02-28', 'stage' => 'now',
        ])->assertCreated()
            ->assertJsonPath('data.goal.title', 'Money roadmap')
            ->assertJsonPath('data.milestones.*.title', ['Clear Fuliza', 'Repay mum', 'KES 28,000'])
            ->assertJsonPath('data.milestones.0.status', 'completed')
            ->json('data.id');

        $this->getJson('/api/v1/roadmap-items')->assertJsonPath('data.0.milestones.2.title', 'KES 28,000');
        $this->patchJson("/api/v1/roadmap-items/{$id}", ['ends_on' => '2026-10-01'])->assertJsonValidationErrors('ends_on');
        $this->patchJson("/api/v1/roadmap-items/{$id}", ['goal_id' => Goal::factory()->create()->id])->assertJsonValidationErrors('goal_id');
    }

    public function test_reorder_sets_stage_and_order(): void
    {
        [$a, $b, $c] = RoadmapItem::factory()->for($this->user)->count(3)->create(['stage' => 'next']);
        $later = RoadmapItem::factory()->for($this->user)->create(['stage' => 'later']);

        $this->postJson('/api/v1/roadmap-items/reorder', ['stage' => 'next', 'ids' => [$later->id, $c->id, $a->id]])
            ->assertOk();

        $this->assertSame(['next', 0], [$later->fresh()->stage->value, $later->fresh()->position]);
        $this->assertSame(1, $c->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame('next', $b->fresh()->stage->value);
    }

    public function test_items_are_private_to_their_owner(): void
    {
        $other = RoadmapItem::factory()->create();

        $this->getJson('/api/v1/roadmap-items')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/roadmap-items/{$other->id}", ['title' => 'Mine'])->assertNotFound();
        $this->deleteJson("/api/v1/roadmap-items/{$other->id}")->assertNotFound();
        $this->postJson('/api/v1/roadmap-items/reorder', ['stage' => 'now', 'ids' => [$other->id]])->assertUnprocessable();
    }

    public function test_roadmap_is_searchable_and_survives_a_backup_round_trip(): void
    {
        RoadmapItem::factory()->for($this->user)->create(['title' => 'Books module', 'stage' => 'done', 'done_on' => '2026-11-08', 'area' => 'Books']);

        $this->getJson('/api/v1/search?q=books+mod')->assertOk()->assertJsonFragment(['kind' => 'roadmap', 'title' => 'Books module']);

        $backup = json_encode($this->getJson('/api/v1/backup')->assertOk()->assertJsonPath('roadmap.0.title', 'Books module')->json());

        $fresh = User::factory()->create();
        $this->actingAs($fresh);
        $this->postJson('/api/v1/backup', ['file' => UploadedFile::fake()->createWithContent('backup.json', $backup)])->assertOk()->assertJsonPath('data.roadmap', 1);
        $this->postJson('/api/v1/backup', ['file' => UploadedFile::fake()->createWithContent('backup.json', $backup)])->assertOk()->assertJsonPath('data.roadmap', 0);

        $item = $fresh->roadmapItems()->sole();
        $this->assertSame(['done', '2026-11-08', 'Books'], [$item->stage->value, $item->done_on->toDateString(), $item->area]);
    }
}

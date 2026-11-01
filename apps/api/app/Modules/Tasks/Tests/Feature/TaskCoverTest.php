<?php

namespace App\Modules\Tasks\Tests\Feature;

use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_task_takes_a_gallery_cover_or_an_upload(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();
        $this->actingAs($user);

        $this->patchJson("/api/v1/tasks/{$task->id}", ['cover' => 'growth-1'])
            ->assertOk()
            ->assertJsonPath('data.cover', ['type' => 'gallery', 'key' => 'growth-1']);
        $this->patchJson("/api/v1/tasks/{$task->id}", ['cover' => 'nope'])->assertJsonValidationErrors('cover');

        $this->post("/api/v1/tasks/{$task->id}/cover", ['image' => UploadedFile::fake()->image('desk.jpg', 1200, 675)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.cover.type', 'upload');
        $path = $task->fresh()->cover_path;
        Storage::disk('local')->assertExists($path);
        $this->get("/api/v1/tasks/{$task->id}/cover")->assertOk();

        $this->patchJson("/api/v1/tasks/{$task->id}", ['cover' => 'fun-2'])->assertJsonPath('data.cover.key', 'fun-2');
        Storage::disk('local')->assertMissing($path);

        $this->deleteJson("/api/v1/tasks/{$task->id}/cover")->assertOk()->assertJsonPath('data.cover', null);
    }

    public function test_covers_are_private_and_removed_with_the_task(): void
    {
        $owner = User::factory()->create();
        $task = Task::factory()->for($owner)->create();
        $this->actingAs($owner)->post("/api/v1/tasks/{$task->id}/cover", ['image' => UploadedFile::fake()->image('a.png', 800, 450)], ['Accept' => 'application/json'])->assertOk();
        $path = $task->fresh()->cover_path;

        $this->actingAs(User::factory()->create());
        $this->get("/api/v1/tasks/{$task->id}/cover")->assertNotFound();
        $this->post("/api/v1/tasks/{$task->id}/cover", ['image' => UploadedFile::fake()->image('b.png', 800, 450)], ['Accept' => 'application/json'])->assertNotFound();

        $this->actingAs($owner)->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();
        Storage::disk('local')->assertMissing($path);
    }
}

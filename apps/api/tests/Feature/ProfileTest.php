<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_change_requires_the_current_password_and_the_policy(): void
    {
        $user = User::factory()->create(['password' => 'Old-Password-123']);
        $this->actingAs($user);

        $this->putJson('/api/v1/profile/password', [
            'current_password' => 'wrong', 'password' => 'New-Password-456', 'password_confirmation' => 'New-Password-456',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->putJson('/api/v1/profile/password', [
            'current_password' => 'Old-Password-123', 'password' => 'weak', 'password_confirmation' => 'weak',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->putJson('/api/v1/profile/password', [
            'current_password' => 'Old-Password-123', 'password' => 'New-Password-456', 'password_confirmation' => 'New-Password-456',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('New-Password-456', $user->fresh()->password));
    }

    public function test_avatar_upload_is_private_and_replaces_the_previous_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);

        $first = $this->post('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 200, 200)], ['Accept' => 'application/json'])
            ->assertOk()->json('data.avatar_url');
        $firstPath = $user->fresh()->avatar_path;
        $this->assertStringStartsWith('/api/v1/profile/avatar', $first);

        $this->post('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('me2.jpg', 200, 200)], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($user->fresh()->avatar_path);

        $this->get('/api/v1/profile/avatar')->assertOk();
        $this->deleteJson('/api/v1/profile/avatar')->assertJsonPath('data.avatar_url', null);
        $this->getJson('/api/v1/profile/avatar')->assertNotFound();
    }

    public function test_avatar_rejects_svg_and_non_images(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post('/api/v1/profile/avatar', ['avatar' => $svg], ['Accept' => 'application/json'])->assertUnprocessable();

        $txt = UploadedFile::fake()->create('notes.txt', 1, 'text/plain');
        $this->post('/api/v1/profile/avatar', ['avatar' => $txt], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_goal_tone_and_task_highlight_round_trip(): void
    {
        $this->actingAs(User::factory()->create());

        $goal = $this->postJson('/api/v1/goals', ['title' => 'Client A', 'tone' => 'violet'])->assertJsonPath('data.tone', 'violet')->json('data.id');
        $this->postJson('/api/v1/goals', ['title' => 'Bad', 'tone' => 'neon'])->assertUnprocessable();

        $this->postJson('/api/v1/tasks', ['title' => 'Star me', 'goal_id' => $goal, 'is_highlighted' => true])
            ->assertJsonPath('data.is_highlighted', true)
            ->assertJsonPath('data.goal.tone', 'violet');

        $this->getJson('/api/v1/tasks?highlighted=1')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/dashboard')->assertJsonCount(1, 'data.highlighted_tasks');
    }
}

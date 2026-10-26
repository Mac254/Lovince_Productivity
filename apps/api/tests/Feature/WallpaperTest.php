<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WallpaperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function upload(string $name = 'beach.jpg', int $w = 1920, int $h = 1080)
    {
        return $this->post('/api/v1/wallpapers', ['image' => UploadedFile::fake()->image($name, $w, $h)], ['Accept' => 'application/json']);
    }

    public function test_upload_list_view_and_delete(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $id = $this->upload('Mount Kenya at dawn.jpg')
            ->assertCreated()
            ->assertJsonPath('data.name', 'Mount Kenya at dawn')
            ->assertJsonMissingPath('data.path')
            ->json('data.id');

        $this->getJson('/api/v1/wallpapers')->assertJsonCount(1, 'data');
        $this->get("/api/v1/wallpapers/{$id}/image")->assertOk();

        $path = $user->wallpapers()->first()->path;
        $this->deleteJson("/api/v1/wallpapers/{$id}")->assertNoContent();
        Storage::disk('local')->assertMissing($path);
        $this->getJson('/api/v1/wallpapers')->assertJsonCount(0, 'data');
    }

    public function test_wallpapers_are_private(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $id = $this->upload()->json('data.id');

        $this->actingAs(User::factory()->create());
        $this->get("/api/v1/wallpapers/{$id}/image")->assertNotFound();
        $this->deleteJson("/api/v1/wallpapers/{$id}")->assertNotFound();
        $this->getJson('/api/v1/wallpapers')->assertJsonCount(0, 'data');
    }

    public function test_rejects_svg_tiny_images_and_too_many(): void
    {
        $this->actingAs(User::factory()->create());

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post('/api/v1/wallpapers', ['image' => $svg], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->upload('tiny.png', 100, 100)->assertUnprocessable();

        for ($i = 0; $i < Contract::WALLPAPER_LIMIT; $i++) {
            $this->upload("w{$i}.jpg")->assertCreated();
        }
        $this->upload('one-too-many.jpg')->assertUnprocessable()->assertJsonValidationErrors('image');
    }
}

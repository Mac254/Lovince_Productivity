<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\OwnerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $password = 'Strong-Password-42'): void
    {
        config(['focus.owner' => ['name' => 'Owner', 'email' => 'owner@example.com', 'password' => $password]]);
    }

    public function test_owner_is_created_from_config_with_a_hashed_password(): void
    {
        $this->owner();

        $this->seed(OwnerSeeder::class);

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertNotSame('Strong-Password-42', $user->password);
        $this->assertTrue(Hash::check('Strong-Password-42', $user->password));
    }

    public function test_existing_owner_is_never_modified(): void
    {
        $this->owner();
        $existing = User::factory()->create(['email' => 'owner@example.com', 'password' => 'Changed-Later-99']);

        $this->seed(OwnerSeeder::class);

        $this->assertTrue(Hash::check('Changed-Later-99', $existing->fresh()->password));
        $this->assertSame(1, User::count());
    }

    public function test_weak_owner_password_is_refused_in_production(): void
    {
        $this->owner('weakpass');
        $this->app->detectEnvironment(fn () => 'production');

        // Called directly: `db:seed` would stop at its production confirmation prompt.
        $this->expectException(RuntimeException::class);
        $this->app->make(OwnerSeeder::class)->run();
    }

    public function test_owner_is_skipped_without_credentials(): void
    {
        config(['focus.owner' => ['name' => null, 'email' => null, 'password' => null]]);

        $this->seed(OwnerSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_demo_data_fills_an_empty_account_once(): void
    {
        $this->owner();
        $this->seed(OwnerSeeder::class);
        $user = User::firstOrFail();

        $this->seed(DemoSeeder::class);
        $tasks = $user->tasks()->count();
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThan(10, $tasks);
        $this->assertSame($tasks, $user->tasks()->count());
        $this->assertSame(4, $user->goals()->count());

        $this->actingAs($user)->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.integration.development_completed', 3)
            ->assertJsonCount(3, 'data.integration.integration_pending');
    }
}

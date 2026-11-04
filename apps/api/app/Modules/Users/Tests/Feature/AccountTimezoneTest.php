<?php

namespace App\Modules\Users\Tests\Feature;

use App\Modules\Reports\Jobs\GenerateDailyReport;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AccountTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_account_starts_its_day_in_its_own_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 22:30:00', 'UTC'));
        $user = User::factory()->create();
        $user->forceFill(['timezone' => 'Africa/Nairobi'])->save();

        $this->actingAs($user)->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.timezone', 'Africa/Nairobi')
            ->assertJsonPath('meta.session.timezone', 'Africa/Nairobi');
        $this->getJson('/api/v1/dashboard')->assertJsonPath('data.date', '2026-11-03');

        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web')->getJson('/api/v1/dashboard')->assertJsonPath('data.date', '2026-11-02');
    }

    public function test_changing_the_timezone_keeps_the_moments_stored_times_record(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00', 'UTC'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->for($user)->create(['completed_at' => '2026-11-02 08:00:00', 'status' => 'completed']);
        $untouched = Task::factory()->for($other)->create(['completed_at' => '2026-11-02 08:00:00', 'status' => 'completed']);
        $schedule = $user->schedules()->create(['name' => 'Week', 'days' => [1]]);
        $item = $schedule->items()->create(['title' => 'Plan', 'start_minute' => 540, 'minutes' => 60]);
        DB::table('schedule_items')->where('id', $item->id)->update(['created_at' => '2026-11-01 21:30:00']);

        $this->actingAs($user)->patchJson('/api/v1/profile', ['timezone' => 'Asia/Tokyo'])
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Tokyo')
            ->assertJsonPath('data.name', $user->name);

        $this->assertSame('2026-11-02 17:00:00', DB::table('tasks')->where('id', $task->id)->value('completed_at'));
        $this->assertSame('2026-11-02 06:30:00', DB::table('schedule_items')->where('id', $item->id)->value('created_at'));
        $this->assertSame('2026-11-02 08:00:00', DB::table('tasks')->where('id', $untouched->id)->value('completed_at'));
        $this->assertSame('2026-11-02 18:00:00', DB::table('users')->where('id', $user->id)->value('updated_at'));

        $this->patchJson('/api/v1/profile', ['timezone' => 'Asia/Tokyo'])->assertOk();
        $this->assertSame('2026-11-02 17:00:00', DB::table('tasks')->where('id', $task->id)->value('completed_at'));
    }

    public function test_only_real_timezones_are_accepted(): void
    {
        $this->actingAs(User::factory()->create());

        $this->patchJson('/api/v1/profile', ['timezone' => 'Mars/Olympus'])->assertJsonValidationErrors('timezone');
        $this->patchJson('/api/v1/profile', ['timezone' => 'Asia/Calcutta'])->assertOk();
        $this->patchJson('/api/v1/profile', ['name' => ''])->assertJsonValidationErrors('name');
    }

    public function test_reports_are_queued_as_each_accounts_day_ends_and_run_in_its_timezone(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-02 20:55:00', 'UTC'));
        $nairobi = User::factory()->create(['created_at' => '2026-10-01 00:00:00']);
        $nairobi->forceFill(['timezone' => 'Africa/Nairobi'])->save();
        $utc = User::factory()->create(['created_at' => '2026-10-01 00:00:00']);

        $this->artisan('focus:daily-report', ['--due' => true])->assertSuccessful();
        Queue::assertPushed(GenerateDailyReport::class, 1);
        Queue::assertPushed(fn (GenerateDailyReport $job) => $job->uniqueId() === "{$nairobi->id}:2026-11-02");

        Carbon::setTestNow(Carbon::parse('2026-11-02 21:05:00', 'UTC'));
        $this->artisan('focus:daily-report', ['--missing' => 1, '--due' => true])->assertSuccessful();
        Queue::assertPushed(fn (GenerateDailyReport $job) => $job->uniqueId() === "{$nairobi->id}:2026-11-02");
        Queue::assertNotPushed(fn (GenerateDailyReport $job) => $job->user->is($utc));

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());
    }
}

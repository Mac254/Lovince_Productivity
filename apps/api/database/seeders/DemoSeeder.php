<?php

namespace Database\Seeders;

use App\Enums\GoalTimeframe;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Realistic sample goals and tasks for local development, dated around today so every
 * dashboard section has something to show. Only fills an account that has no data yet.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demo data is only seeded in local or testing environments.');

            return;
        }

        $user = $this->target();

        if (! $user) {
            $this->command?->info('No account to fill with demo data — set FOCUS_OWNER_* or run `php artisan focus:create-user`.');

            return;
        }

        if ($user->goals()->exists() || $user->tasks()->exists()) {
            $this->command?->info("{$user->email} already has data — demo data skipped.");

            return;
        }

        DB::transaction(fn () => $this->fill($user));

        $this->command?->info("Seeded demo goals and tasks for {$user->email}.");
    }

    private function target(): ?User
    {
        $email = config('focus.owner.email');

        return ($email ? User::query()->where('email', $email)->first() : null) ?? User::query()->oldest('id')->first();
    }

    private function fill(User $user): void
    {
        $today = today();
        $day = fn (int $offset): string => $today->copy()->addDays($offset)->toDateString();

        $goals = [
            'portal' => $user->goals()->create([
                'title' => 'Bizsure Client Portal V2',
                'description' => 'Redesign the client portal, then integrate it with the policy API.',
                'timeframe' => GoalTimeframe::Weekly,
                'start_date' => $today->copy()->startOfWeek()->toDateString(),
                'target_date' => $today->copy()->endOfWeek()->toDateString(),
            ]),
            'blog' => $user->goals()->create([
                'title' => 'Blog website launch',
                'description' => 'Ship the new blog and connect it to the CMS.',
                'timeframe' => GoalTimeframe::LongTerm,
                'start_date' => $day(-21),
                'target_date' => $day(24),
            ]),
            'writing' => $user->goals()->create([
                'title' => 'Weekly writing habit',
                'timeframe' => GoalTimeframe::Weekly,
                'start_date' => $today->copy()->startOfWeek()->toDateString(),
                'target_date' => $today->copy()->endOfWeek()->toDateString(),
            ]),
            'learning' => $user->goals()->create([
                'title' => 'Level up on Laravel and Vue testing',
                'timeframe' => GoalTimeframe::LongTerm,
                'start_date' => $day(-10),
                'target_date' => $day(50),
            ]),
        ];

        // [title, status, priority, category, goal, due offset, description, completed days ago]
        $tasks = [
            ['Bizsure Client Portal V2 redesign', TaskStatus::Completed, TaskPriority::High, TaskCategory::Development, 'portal', -2, 'New dashboard, policy list and claims flow in the monochrome system.', 2],
            ['Bizsure Client Portal V2 integration', TaskStatus::NotStarted, TaskPriority::High, TaskCategory::Integration, 'portal', 7, 'Wire the redesigned screens to the policy and claims API. Scheduled for next week.', null],
            ['Write portal release notes', TaskStatus::NotStarted, TaskPriority::Medium, TaskCategory::General, 'portal', 0, null, null],
            ['Portal accessibility pass', TaskStatus::InProgress, TaskPriority::Medium, TaskCategory::Development, 'portal', 1, 'Keyboard order, focus states and contrast on the new screens.', null],
            ['Blog website development', TaskStatus::Completed, TaskPriority::Medium, TaskCategory::Development, 'blog', -6, null, 5],
            ['Blog website integration', TaskStatus::InProgress, TaskPriority::Urgent, TaskCategory::Integration, 'blog', 0, 'Connect the CMS API and verify the publish webhooks.', null],
            ['Set up blog analytics', TaskStatus::NotStarted, TaskPriority::Low, TaskCategory::Integration, 'blog', 12, null, null],
            ['Draft weekly blog post', TaskStatus::NotStarted, TaskPriority::Low, TaskCategory::General, 'writing', 2, null, null],
            ['Outline next week’s post', TaskStatus::Completed, TaskPriority::Low, TaskCategory::General, 'writing', 0, null, 0],
            ['Write feature tests for task filters', TaskStatus::Completed, TaskPriority::Medium, TaskCategory::Development, 'learning', -1, null, 1],
            ['Try Vitest component testing', TaskStatus::NotStarted, TaskPriority::Low, TaskCategory::General, 'learning', 9, null, null],
            ['Fix invoice PDF rendering', TaskStatus::Blocked, TaskPriority::Urgent, TaskCategory::Development, null, -2, 'Waiting on the font licence from the client.', null],
            ['Review pull requests for auth module', TaskStatus::InProgress, TaskPriority::High, TaskCategory::General, null, 0, null, null],
            ['Plan Q4 roadmap', TaskStatus::NotStarted, TaskPriority::Medium, TaskCategory::General, null, 5, null, null],
            ['Update dependencies', TaskStatus::Completed, TaskPriority::Low, TaskCategory::General, null, 0, null, 0],
            ['Renew domain and SSL certificates', TaskStatus::NotStarted, TaskPriority::High, TaskCategory::General, null, 3, null, null],
            ['Back up production database and test a restore', TaskStatus::NotStarted, TaskPriority::High, TaskCategory::General, null, -1, null, null],
            ['Old onboarding emails', TaskStatus::Cancelled, TaskPriority::Low, TaskCategory::General, null, -4, 'Replaced by the new onboarding flow.', null],
            ['Inbox zero', TaskStatus::NotStarted, TaskPriority::Low, TaskCategory::General, null, null, null, null],
        ];

        foreach ($tasks as [$title, $status, $priority, $category, $goal, $due, $description, $completedDaysAgo]) {
            $task = $user->tasks()->make([
                'title' => $title,
                'description' => $description,
                'status' => $status,
                'priority' => $priority,
                'category' => $category,
                'goal_id' => $goal ? $goals[$goal]->id : null,
                'due_date' => $due === null ? null : $day($due),
            ]);

            if ($completedDaysAgo !== null) {
                $task->completed_at = Carbon::now()->subDays($completedDaysAgo)->setTime(11, 30);
            }

            $task->save();
        }
    }
}

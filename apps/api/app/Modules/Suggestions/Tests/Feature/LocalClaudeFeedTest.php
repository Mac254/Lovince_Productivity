<?php

namespace App\Modules\Suggestions\Tests\Feature;

use App\Modules\Suggestions\Feeds\LocalClaude;
use App\Modules\Suggestions\Models\PooledIdea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class LocalClaudeFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['suggestions.feed.driver' => LocalClaude::class, 'suggestions.feed.claude.binary' => '/usr/bin/claude']);
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
    }

    private function reply(array $ideas, bool $error = false): string
    {
        return json_encode(['type' => 'result', 'is_error' => $error, 'result' => '', 'structured_output' => ['ideas' => $ideas]]);
    }

    private function fetch(): void
    {
        $this->artisan('suggestions:fetch-ideas')->assertSuccessful();
    }

    public function test_ideas_written_by_claude_pass_the_same_checks(): void
    {
        Process::fake(['*' => Process::result($this->reply([
            ['title' => 'Write a letter to your future self', 'category' => 'wellbeing', 'minutes' => 20],
            ['title' => 'Learn three chords on a ukulele', 'category' => 'fun', 'minutes' => 30],
            ['title' => 'Go to a bar with friends', 'category' => 'social', 'minutes' => 60],
            ['title' => 'Read about <script>', 'category' => 'growth', 'minutes' => 10],
            ['title' => 'Plan a picnic with Taylor', 'category' => 'social', 'minutes' => 15],
            ['title' => 'Stretch your shoulders for five minutes', 'category' => 'unknown', 'minutes' => 5],
            ['title' => 'Take a slow walk around the block', 'category' => 'health', 'minutes' => 999],
        ]))]);

        $this->fetch();

        $ideas = PooledIdea::orderBy('id')->get();
        $this->assertSame(['Write a letter to your future self', 'Learn three chords on a ukulele', 'Take a slow walk around the block'], $ideas->pluck('title')->all());
        $this->assertSame([20, 30, null], $ideas->pluck('minutes')->all());
        $this->assertSame('LocalClaude', $ideas[0]->feed);
        Http::assertNothingSent();
    }

    public function test_claude_runs_headless_without_tools_from_an_empty_folder(): void
    {
        Process::fake(['*' => Process::result($this->reply([]))]);

        $this->fetch();

        Process::assertRan(function (PendingProcess $process) {
            $command = $process->command;

            return $command[0] === '/usr/bin/claude' && $command[1] === '-p'
                && str_contains($command[2], 'Start with one of these verbs')
                && $command[array_search('--tools', $command) + 1] === ''
                && $command[array_search('--setting-sources', $command) + 1] === ''
                && in_array('--strict-mcp-config', $command, true)
                && in_array('--no-session-persistence', $command, true)
                && in_array('--max-budget-usd', $command, true)
                && json_decode($command[array_search('--json-schema', $command) + 1], true)['properties']['ideas']['maxItems'] === 10
                && $process->path === storage_path('framework/suggestions-feed')
                && $process->timeout === 120;
        });
    }

    public function test_failures_give_no_ideas_and_do_not_break_the_job(): void
    {
        Process::fake(['*' => Process::sequence()
            ->push(Process::result('', 'command not found', 127))
            ->push(Process::result($this->reply([['title' => 'Write a poem', 'category' => 'fun', 'minutes' => 10]], error: true)))
            ->push(Process::result('Sorry, I cannot help with that.'))
            ->push(Process::result(json_encode(['is_error' => false, 'structured_output' => ['ideas' => 'Write a poem']]))),
        ]);

        foreach (range(1, 4) as $run) {
            $this->fetch();
        }

        $this->assertSame(0, PooledIdea::count());
    }

    public function test_a_timeout_is_quiet(): void
    {
        Process::fake(['*' => fn () => throw new ProcessTimedOutException(new SymfonyProcess(['claude']), ProcessTimedOutException::TYPE_GENERAL)]);

        $this->fetch();

        $this->assertSame(0, PooledIdea::count());
    }
}

<?php

namespace App\Modules\Suggestions\Feeds;

use App\Modules\Suggestions\Contracts\IdeaFeed;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Suggestions\Models\PooledIdea;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Asks the Claude Code CLI on this machine to write fresh ideas. It runs headless with no tools, MCP
 * servers, settings or saved session, from an empty folder, under a time and spending cap, and its
 * answer must match a JSON schema. The ideas still go through every `IdeaPool` check, so the model
 * is trusted no more than any other feed. The CLI is found on the PATH or in `~/.local/bin` unless
 * `suggestions.feed.claude.binary` names it. A missing CLI, a timeout or a bad answer gives no ideas.
 */
class LocalClaude implements IdeaFeed
{
    public function fetch(int $count): array
    {
        $config = config('suggestions.feed.claude');
        $folder = storage_path('framework/suggestions-feed');
        $binary = $config['binary'] ?: (new ExecutableFinder)->find('claude', null, [getenv('HOME').'/.local/bin', '/usr/local/bin']);
        if ($binary === null) {
            return [];
        }

        try {
            File::ensureDirectoryExists($folder);
            $result = Process::path($folder)->timeout($config['timeout'])->input('')->run([
                $binary, '-p', $this->prompt($count),
                '--model', $config['model'],
                '--tools', '',
                '--strict-mcp-config',
                '--setting-sources', '',
                '--no-session-persistence',
                '--max-budget-usd', (string) $config['max_budget_usd'],
                '--output-format', 'json',
                '--json-schema', json_encode($this->schema($count)),
            ]);
        } catch (Throwable $e) {
            Log::info('Suggestions feed unavailable', ['error' => class_basename($e)]);

            return [];
        }

        $reply = $result->successful() ? json_decode($result->output(), true, 16) : null;
        if (! is_array($reply) || ($reply['is_error'] ?? true) !== false || ! is_array($reply['structured_output']['ideas'] ?? null)) {
            return [];
        }

        return collect($reply['structured_output']['ideas'])
            ->filter(fn ($idea) => is_array($idea) && is_string($idea['title'] ?? null) && is_string($idea['category'] ?? null))
            ->take($count)
            ->map(fn (array $idea) => [
                'title' => $idea['title'],
                'category' => $idea['category'],
                'minutes' => is_int($idea['minutes'] ?? null) ? $idea['minutes'] : null,
            ])
            ->values()
            ->all();
    }

    private function prompt(int $count): string
    {
        $feed = config('suggestions.feed');
        $taken = PooledIdea::query()->latest('id')->limit(60)->pluck('title')
            ->concat(collect(config('suggestions.library'))->shuffle()->take(40)->pluck('title'))
            ->implode('; ');

        return implode("\n", [
            "Write {$count} short ideas for small things a person could do today to make their day better, for a personal productivity app.",
            'Spread them across these categories: '.implode(', ', array_column(SuggestionCategory::cases(), 'value')).'.',
            'Rules for every idea:',
            '- Start with one of these verbs: '.implode(', ', $feed['verbs']).'.',
            "- 2 to 12 words, at most {$feed['max_length']} characters, sentence case, plain letters only, no names, brands, emoji or links.",
            '- Free or nearly free, safe for anyone, doable at home or nearby within two hours, needing nothing the person may not own.',
            '- Nothing about alcohol, drugs, gambling, money, shopping, driving, weapons, medical topics, pets, risky activities or pranks.',
            '- Different from each other and from these existing ideas: '.$taken.'.',
            'Give minutes as a realistic estimate between 5 and 120. Variety seed: '.random_int(1, 1_000_000).'.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(int $count): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['ideas'],
            'properties' => ['ideas' => [
                'type' => 'array',
                'maxItems' => $count,
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['title', 'category', 'minutes'],
                    'properties' => [
                        'title' => ['type' => 'string', 'maxLength' => config('suggestions.feed.max_length')],
                        'category' => ['type' => 'string', 'enum' => array_column(SuggestionCategory::cases(), 'value')],
                        'minutes' => ['type' => 'integer', 'minimum' => 5, 'maximum' => 120],
                    ],
                ],
            ]],
        ];
    }
}

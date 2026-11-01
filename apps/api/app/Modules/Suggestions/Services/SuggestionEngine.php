<?php

namespace App\Modules\Suggestions\Services;

use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Suggestions\Contracts\Suggester;
use App\Modules\Suggestions\Enums\SuggestionSource;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Suggestions\Sources\EveningJournal;
use App\Modules\Suggestions\Sources\IdeaLibrary;
use App\Modules\Suggestions\Sources\OverdueTasks;
use App\Modules\Suggestions\Sources\PlanTomorrow;
use App\Modules\Suggestions\Sources\QuietGoals;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SuggestionEngine
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * Refreshes at most once an hour per user, so time-of-day suggestions still appear.
     */
    public function refreshIfStale(User $user): void
    {
        $now = CarbonImmutable::now();
        Cache::remember("suggestions:{$user->id}:{$now->format('Y-m-d-H')}", $now->endOfHour(), function () use ($user, $now) {
            $this->refresh($user, $now);

            return true;
        });
    }

    /**
     * Adds what the sources propose now and withdraws open insights whose reason has passed.
     */
    public function refresh(User $user, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $settings = $this->settings->resolve($user->settings)['suggestions'];
        if (! $settings['enabled']) {
            return 0;
        }

        $drafts = collect($this->sources((int) $settings['ideas_per_day']))
            ->flatMap(fn (Suggester $source) => iterator_to_array($source->drafts($user, $now), false))
            ->keyBy('key');

        return DB::transaction(function () use ($user, $drafts) {
            Suggestion::query()
                ->whereBelongsTo($user)
                ->where('source', SuggestionSource::Insight)
                ->where('status', SuggestionStatus::New)
                ->whereNotIn('key', $drafts->keys())
                ->delete();

            $known = Suggestion::query()->whereBelongsTo($user)->whereIn('key', $drafts->keys())->pluck('key')->all();

            return $drafts->except($known)->each(fn ($draft) => $user->suggestions()->create([
                'source' => $draft->source,
                'key' => $draft->key,
                'title' => $draft->title,
                'body' => $draft->body,
                'category' => $draft->category,
                'action' => $draft->action,
            ]))->count();
        });
    }

    /**
     * @return list<Suggester>
     */
    private function sources(int $ideasPerDay): array
    {
        return [new PlanTomorrow, new OverdueTasks, new QuietGoals, new EveningJournal, new IdeaLibrary($ideasPerDay)];
    }
}

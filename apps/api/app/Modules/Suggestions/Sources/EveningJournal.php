<?php

namespace App\Modules\Suggestions\Sources;

use App\Modules\Journal\Models\JournalEntry;
use App\Modules\Suggestions\Contracts\Suggester;
use App\Modules\Suggestions\Contracts\SuggestionDraft;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;

class EveningJournal implements Suggester
{
    public function drafts(User $user, CarbonImmutable $now): iterable
    {
        if ($now->hour < 18 || JournalEntry::query()->whereBelongsTo($user)->whereDate('date', $now->toDateString())->exists()) {
            return;
        }

        yield new SuggestionDraft(
            key: "journal:{$now->toDateString()}",
            title: 'Write three good things about today',
            category: SuggestionCategory::Wellbeing,
            body: 'Two minutes in the journal before bed. Small wins add up when you can see them.',
            action: ['type' => 'open', 'route' => 'journal'],
        );
    }
}

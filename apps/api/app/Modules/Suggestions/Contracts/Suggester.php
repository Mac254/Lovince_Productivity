<?php

namespace App\Modules\Suggestions\Contracts;

use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;

/**
 * Reads the user's data and proposes suggestions. Keys identify a suggestion, so the same key is
 * never offered twice; a source repeats an idea by putting a date or week in its key.
 */
interface Suggester
{
    /**
     * @return iterable<SuggestionDraft>
     */
    public function drafts(User $user, CarbonImmutable $now): iterable;
}

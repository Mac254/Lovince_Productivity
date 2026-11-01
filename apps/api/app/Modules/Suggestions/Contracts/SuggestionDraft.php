<?php

namespace App\Modules\Suggestions\Contracts;

use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Suggestions\Enums\SuggestionSource;

final readonly class SuggestionDraft
{
    /**
     * @param  array<string, mixed>|null  $action  `{type: 'task'}` (default), `{type: 'apply_schedule', schedule_id, date}` or `{type: 'open', route}`.
     */
    public function __construct(
        public string $key,
        public string $title,
        public SuggestionCategory $category,
        public ?string $body = null,
        public ?array $action = null,
        public SuggestionSource $source = SuggestionSource::Insight,
    ) {}
}

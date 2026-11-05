<?php

namespace App\Modules\Suggestions\Models;

use App\Modules\Suggestions\Enums\SuggestionCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * An idea fetched from the internet feed. Shared by every user and offered through `IdeaLibrary`
 * alongside the built-in library.
 */
#[Fillable(['key', 'category', 'title', 'minutes', 'feed'])]
class PooledIdea extends Model
{
    protected $table = 'suggestion_ideas';

    protected function casts(): array
    {
        return ['category' => SuggestionCategory::class, 'minutes' => 'integer'];
    }

    /**
     * @return array<string, mixed> Shaped like an entry of `config('suggestions.library')`.
     */
    public function toIdea(): array
    {
        return array_filter([
            'key' => $this->key,
            'category' => $this->category->value,
            'title' => $this->title,
            'minutes' => $this->minutes,
        ]);
    }
}

<?php

namespace App\Modules\Suggestions\Models;

use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Suggestions\Enums\SuggestionSource;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['source', 'key', 'title', 'body', 'category', 'status', 'action', 'task_id'])]
class Suggestion extends Model
{
    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return [
            'source' => SuggestionSource::class,
            'category' => SuggestionCategory::class,
            'status' => SuggestionStatus::class,
            'action' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}

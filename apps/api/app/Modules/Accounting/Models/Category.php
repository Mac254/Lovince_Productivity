<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Database\Factories\CategoryFactory;
use App\Modules\Accounting\Enums\CategoryKind;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kind', 'name', 'is_default', 'archived_at'])]
#[Table('accounting_categories')]
#[UseFactory(CategoryFactory::class)]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => CategoryKind::class,
            'is_default' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsed(): bool
    {
        return Transaction::query()->where('category_id', $this->id)->exists()
            || JournalLine::query()->where('category_id', $this->id)->exists();
    }
}

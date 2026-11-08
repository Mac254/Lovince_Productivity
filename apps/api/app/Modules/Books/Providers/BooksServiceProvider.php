<?php

namespace App\Modules\Books\Providers;

use App\Modules\Books\Models\Book;
use App\Modules\Books\Models\BookReading;
use App\Modules\Books\Policies\BookPolicy;
use App\Modules\Books\Policies\BookReadingPolicy;
use App\Modules\Goals\Models\Goal;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class BooksServiceProvider extends ModuleProvider
{
    protected array $policies = [
        Book::class => BookPolicy::class,
        BookReading::class => BookReadingPolicy::class,
    ];

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('books', fn (User $user) => $user->hasMany(Book::class));
        Goal::resolveRelationUsing('books', fn (Goal $goal) => $goal->hasMany(Book::class));
        Task::resolveRelationUsing('book', fn (Task $task) => $task->belongsTo(Book::class));
    }
}

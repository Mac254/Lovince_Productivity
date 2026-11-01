<?php

namespace App\Modules\Journal\Providers;

use App\Modules\Journal\Models\JournalEntry;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class JournalServiceProvider extends ModuleProvider
{
    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('journalEntries', fn (User $user) => $user->hasMany(JournalEntry::class));
    }
}

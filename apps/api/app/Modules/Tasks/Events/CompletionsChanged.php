<?php

namespace App\Modules\Tasks\Events;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * A task was completed on, or reopened from, these earlier days.
 */
class CompletionsChanged
{
    use Dispatchable;

    /**
     * @param  list<Carbon>  $days
     */
    public function __construct(public User $user, public array $days) {}
}

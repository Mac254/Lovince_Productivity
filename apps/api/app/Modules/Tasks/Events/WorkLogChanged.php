<?php

namespace App\Modules\Tasks\Events;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * A user's work log entries for these days were added, changed, moved or removed.
 */
class WorkLogChanged
{
    use Dispatchable;

    /**
     * @param  list<Carbon>  $days
     */
    public function __construct(public User $user, public array $days) {}
}

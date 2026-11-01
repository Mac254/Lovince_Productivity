<?php

namespace App\Modules\Blockers\Policies;

use App\Modules\Blockers\Models\Blocker;
use App\Modules\Users\Models\User;
use Illuminate\Auth\Access\Response;

class BlockerPolicy
{
    public function view(User $user, Blocker $blocker): Response
    {
        return $this->owns($user, $blocker);
    }

    public function update(User $user, Blocker $blocker): Response
    {
        return $this->owns($user, $blocker);
    }

    public function delete(User $user, Blocker $blocker): Response
    {
        return $this->owns($user, $blocker);
    }

    /**
     * Respond with 404 rather than 403 so other users' record IDs are not confirmed.
     */
    private function owns(User $user, Blocker $blocker): Response
    {
        return $blocker->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}

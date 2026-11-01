<?php

namespace App\Modules\Goals\Policies;

use App\Modules\Goals\Models\Goal;
use App\Modules\Users\Models\User;
use Illuminate\Auth\Access\Response;

class GoalPolicy
{
    public function view(User $user, Goal $goal): Response
    {
        return $this->owns($user, $goal);
    }

    public function update(User $user, Goal $goal): Response
    {
        return $this->owns($user, $goal);
    }

    public function delete(User $user, Goal $goal): Response
    {
        return $this->owns($user, $goal);
    }

    /**
     * Respond with 404 rather than 403 so other users' record IDs are not confirmed.
     */
    private function owns(User $user, Goal $goal): Response
    {
        return $goal->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}

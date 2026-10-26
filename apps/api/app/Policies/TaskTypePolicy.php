<?php

namespace App\Policies;

use App\Models\TaskType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskTypePolicy
{
    public function update(User $user, TaskType $type): Response
    {
        return $this->owns($user, $type);
    }

    public function delete(User $user, TaskType $type): Response
    {
        return $this->owns($user, $type);
    }

    /**
     * Respond with 404 rather than 403 so other users' record IDs are not confirmed.
     */
    private function owns(User $user, TaskType $type): Response
    {
        return $type->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}

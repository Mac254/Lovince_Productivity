<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    public function view(User $user, Task $task): Response
    {
        return $this->owns($user, $task);
    }

    public function update(User $user, Task $task): Response
    {
        return $this->owns($user, $task);
    }

    public function delete(User $user, Task $task): Response
    {
        return $this->owns($user, $task);
    }

    /**
     * Respond with 404 rather than 403 so other users' record IDs are not confirmed.
     */
    private function owns(User $user, Task $task): Response
    {
        return $task->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}

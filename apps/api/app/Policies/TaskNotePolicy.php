<?php

namespace App\Policies;

use App\Models\TaskNote;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskNotePolicy
{
    public function update(User $user, TaskNote $note): Response
    {
        return $this->owns($user, $note);
    }

    public function delete(User $user, TaskNote $note): Response
    {
        return $this->owns($user, $note);
    }

    /**
     * Respond with 404 rather than 403 so other users' record IDs are not confirmed.
     */
    private function owns(User $user, TaskNote $note): Response
    {
        return $note->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}

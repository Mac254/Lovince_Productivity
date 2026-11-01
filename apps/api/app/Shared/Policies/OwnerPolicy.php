<?php

namespace App\Shared\Policies;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

abstract class OwnerPolicy
{
    public function view(Authenticatable $user, Model $record): Response
    {
        return $this->owns($user, $record);
    }

    public function update(Authenticatable $user, Model $record): Response
    {
        return $this->owns($user, $record);
    }

    public function delete(Authenticatable $user, Model $record): Response
    {
        return $this->owns($user, $record);
    }

    protected function owns(Authenticatable $user, Model $record): Response
    {
        return $record->getAttribute('user_id') === $user->getAuthIdentifier() ? Response::allow() : Response::denyAsNotFound();
    }
}

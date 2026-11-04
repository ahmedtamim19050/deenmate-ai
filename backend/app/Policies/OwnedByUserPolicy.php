<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** API access to personal records is owner-only, with no exception for admin roles. */
class OwnedByUserPolicy
{
    public function update(User $user, Model $model): bool
    {
        return $model->getAttribute('user_id') === $user->id;
    }

    public function delete(User $user, Model $model): bool
    {
        return $model->getAttribute('user_id') === $user->id;
    }
}

<?php

namespace App\Policies;

use App\Models\BoardColumn;
use App\Models\User;

class BoardColumnPolicy
{
    public function view(User $user, BoardColumn $column): bool
    {
        return $column->project->user_id === $user->id;
    }

    public function update(User $user, BoardColumn $column): bool
    {
        return $column->project->user_id === $user->id;
    }

    public function delete(User $user, BoardColumn $column): bool
    {
        return $column->project->user_id === $user->id;
    }
}

<?php

namespace App\Policies;

use App\Models\LogEntry;
use App\Models\User;

class LogEntryPolicy
{
    public function view(User $user, LogEntry $entry): bool
    {
        return $entry->project->user_id === $user->id;
    }

    public function update(User $user, LogEntry $entry): bool
    {
        return $entry->project->user_id === $user->id;
    }

    public function delete(User $user, LogEntry $entry): bool
    {
        return $entry->project->user_id === $user->id;
    }
}

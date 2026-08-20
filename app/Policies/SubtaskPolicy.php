<?php

namespace App\Policies;

use App\Models\Subtask;
use App\Models\User;

class SubtaskPolicy
{
    public function view(User $user, Subtask $subtask): bool
    {
        return $user->can('view', $subtask->task);
    }

    public function execute(User $user, Subtask $subtask): bool
    {
        return $this->view($user, $subtask)
            && $subtask->is_active
            && $subtask->status !== 'cancelled'
            && ! in_array($subtask->task->status, ['pending_review', 'completed', 'cancelled'], true)
            && $subtask->assigned_to === $user->id;
    }
}

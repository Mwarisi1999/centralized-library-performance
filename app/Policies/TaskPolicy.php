<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view tasks');
    }

    public function view(User $user, Task $task): bool
    {
        return $user->can('view tasks')
            && ($task->reviewer_id === $user->id || Task::query()->visibleTo($user)->whereKey($task)->exists());
    }

    public function create(User $user): bool
    {
        return $user->can('create tasks');
    }

    public function createForProject(User $user, Project $project): bool
    {
        return $this->create($user) && $user->can('view', $project);
    }

    public function execute(User $user, Task $task): bool
    {
        return $this->view($user, $task) && $task->taskAssignees()->where('user_id', $user->id)->where('is_active', true)->exists();
    }

    public function addSubtask(User $user, Task $task): bool
    {
        if (! $user->can('create tasks') || ! $this->view($user, $task)
            || in_array($task->status, ['pending_review', 'completed', 'cancelled'], true)) {
            return false;
        }
        $manager = $user->can('view university dashboard') || $user->can('view campus dashboard');

        return $manager || $task->created_by === $user->id || $this->execute($user, $task);
    }

    public function review(User $user, Task $task): bool
    {
        return $task->status === 'pending_review' && $task->reviewer_id === $user->id;
    }
}

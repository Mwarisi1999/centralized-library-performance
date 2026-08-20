<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;

class TaskReviewerResolver
{
    public function resolve(Task $task): ?User
    {
        $assignments = $task->taskAssignees()->where('is_active', true)->orderBy('id')->get();
        $assigneeIds = $assignments->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $current = $assignments->first()?->user;
        $visited = [];

        while ($current && ! in_array($current->id, $visited, true)) {
            $visited[] = $current->id;
            $supervisor = $current->staffProfile?->supervisor;
            if (! $supervisor) {
                return null;
            }
            if ($supervisor->account_status === 'active' && ! in_array($supervisor->id, $assigneeIds, true)) {
                return $supervisor;
            }
            $current = $supervisor;
        }

        return null;
    }
}

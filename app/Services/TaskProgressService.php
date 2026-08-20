<?php

namespace App\Services;

use App\Models\Task;

class TaskProgressService
{
    public function hasAutomaticProgress(Task $task): bool
    {
        return $task->subtasks()->activeForProgress()->exists();
    }

    public function recalculate(Task $task): Task
    {
        $subtasks = $task->subtasks()->activeForProgress()->get();
        if ($subtasks->isEmpty()) {
            return $task;
        }
        $progress = round((float) $subtasks->avg('progress_percentage'), 2);
        $status = $task->status;
        if (! in_array($status, ['pending_review', 'completed', 'deferred', 'cancelled'], true)) {
            $status = $progress > 0 ? 'in_progress' : 'not_started';
        }
        $task->update(['progress_percentage' => $progress, 'status' => $status]);

        return $task->refresh();
    }
}

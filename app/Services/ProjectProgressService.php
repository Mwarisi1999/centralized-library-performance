<?php

namespace App\Services;

use App\Models\Project;

class ProjectProgressService
{
    public function recalculate(Project $project): Project
    {
        if ($project->progress_method !== 'tasks'
            || ! $project->is_active
            || $project->status === 'cancelled') {
            return $project;
        }

        $tasks = $project->tasks()
            ->where('is_active', true)
            ->where('status', '!=', 'cancelled')
            ->get(['status', 'progress_percentage']);

        if ($tasks->isEmpty()) {
            $status = $project->status === 'planned' ? 'planned' : 'not_started';
            $project->update(['progress_percentage' => 0, 'status' => $status, 'completed_at' => null]);

            return $project->refresh();
        }

        $progress = round((float) $tasks->avg('progress_percentage'), 2);
        $allCompleted = $tasks->every(fn ($task) => $task->status === 'completed');
        $started = $tasks->contains(fn ($task) => (float) $task->progress_percentage > 0
            || in_array($task->status, ['in_progress', 'pending_review'], true));

        $status = $allCompleted
            ? 'completed'
            : ($started ? 'in_progress' : ($project->status === 'planned' ? 'planned' : 'not_started'));
        $project->update([
            'progress_percentage' => $allCompleted ? 100 : $progress,
            'status' => $status,
            'completed_at' => $allCompleted ? ($project->completed_at ?? now()) : null,
        ]);

        return $project->refresh();
    }
}

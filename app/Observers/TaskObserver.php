<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\ProjectProgressService;

class TaskObserver
{
    public function __construct(private readonly ProjectProgressService $progress) {}

    public function saved(Task $task): void
    {
        $this->progress->recalculate($task->project);
    }

    public function deleted(Task $task): void
    {
        $this->progress->recalculate($task->project);
    }

    public function restored(Task $task): void
    {
        $this->progress->recalculate($task->project);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\WorkflowNotificationService;
use Illuminate\Console\Command;

class SendTaskDueNotifications extends Command
{
    protected $signature = 'notifications:send-task-reminders {--days=3 : Number of days before a due date}';

    protected $description = 'Create deduplicated in-app reminders for due and overdue task assignments';

    public function handle(WorkflowNotificationService $notifications): int
    {
        $days = max(0, min(30, (int) $this->option('days')));
        $tasks = Task::query()->where('is_active', true)->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_date')->whereDate('due_date', '<=', today()->addDays($days))
            ->with(['assignees' => fn ($query) => $query->where('users.account_status', 'active')])->get();

        $processed = 0;
        foreach ($tasks as $task) {
            foreach ($task->assignees as $assignee) {
                $overdue = $task->due_date->isBefore(today());
                $notifications->send(
                    $assignee,
                    $overdue ? 'task_overdue' : 'task_due_soon',
                    $overdue ? 'Task overdue' : 'Task due soon',
                    "{$task->task_code}: {$task->title} ".($overdue ? 'is overdue.' : 'is due '.$task->due_date->format('d M Y').'.'),
                    route('tasks.show', $task),
                    'task-reminder:'.$task->id.':'.$assignee->id.':'.today()->toDateString(),
                    $overdue ? 'danger' : 'warning',
                );
                $processed++;
            }
        }

        $this->info("Processed {$processed} task reminder recipients.");

        return self::SUCCESS;
    }
}

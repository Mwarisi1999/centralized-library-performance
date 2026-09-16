<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\WorkflowNotificationService;
use Illuminate\Console\Command;

class SendTaskDueNotifications extends Command
{
    protected $signature = 'notifications:send-task-reminders';

    protected $description = 'Create milestone-based reminders for due and overdue task assignments';

    public function handle(WorkflowNotificationService $notifications): int
    {
        $beforeDueDays = collect(config('notifications.deadline_reminders.before_due_days', [3, 1, 0]))
            ->map(fn ($day) => max(0, (int) $day))->unique()->values();
        $overdueDays = collect(config('notifications.deadline_reminders.overdue_days', [1, 7, 14, 30]))
            ->map(fn ($day) => max(1, (int) $day))->unique()->values();
        $maximumFutureDays = max(0, (int) $beforeDueDays->max());
        $maximumOverdueDays = max(0, (int) $overdueDays->max());

        $tasks = Task::query()->where('is_active', true)->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [today()->subDays($maximumOverdueDays), today()->addDays($maximumFutureDays)])
            ->with(['assignees' => fn ($query) => $query->where('users.account_status', 'active')])->get();

        $processed = 0;
        foreach ($tasks as $task) {
            foreach ($task->assignees as $assignee) {
                $daysUntilDue = (int) today()->diffInDays($task->due_date, false);
                $overdue = $daysUntilDue < 0;
                $milestone = $overdue ? abs($daysUntilDue) : $daysUntilDue;

                if ($overdue ? ! $overdueDays->contains($milestone) : ! $beforeDueDays->contains($milestone)) {
                    continue;
                }

                $notifications->send(
                    $assignee,
                    $overdue ? 'task_overdue' : 'task_due_soon',
                    $overdue ? 'Task overdue' : 'Task due soon',
                    "{$task->task_code}: {$task->title} ".($overdue ? 'is overdue.' : 'is due '.$task->due_date->format('d M Y').'.'),
                    route('tasks.show', $task),
                    'task-reminder:'.$task->id.':'.$assignee->id.':'.($overdue ? 'overdue-' : 'due-in-').$milestone,
                    $overdue ? 'danger' : 'warning',
                );
                $processed++;
            }
        }

        $this->info("Processed {$processed} task reminder recipients.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Models\WorkEntry;
use App\Models\WorkEntryActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class IndividualDashboardService
{
    public const UPCOMING_DEADLINE_DAYS = 14;

    public const UPCOMING_DEADLINE_LIMIT = 5;

    public const RECENT_ACTIVITY_LIMIT = 8;

    public const URGENT_DEADLINE_DAYS = 3;

    public const ALERT_LIMIT = 8;

    /**
     * @return array{
     *     hours_this_month: string,
     *     active_projects: int,
     *     assigned_tasks: int,
     *     completed_tasks: int,
     *     in_progress_tasks: int,
     *     overdue_tasks: int,
     *     days_reported: int,
     *     completion_rate: float
     * }
     */
    public function summaryFor(User $user, ?array $taskMetrics = null): array
    {
        $monthStart = today()->startOfMonth();
        $monthEnd = today()->endOfMonth();

        $monthlyEntries = WorkEntry::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$monthStart, $monthEnd]);

        $taskMetrics ??= $this->taskMetricsFor($user);
        $assignedTasks = $taskMetrics['total'];
        $completedTasks = $taskMetrics['statuses']['completed'];

        return [
            'hours_this_month' => WorkEntry::formatMinutes((int) (clone $monthlyEntries)->sum('duration_minutes')),
            'active_projects' => $this->activeProjects($user)->count(),
            'assigned_tasks' => $assignedTasks,
            'completed_tasks' => $completedTasks,
            'in_progress_tasks' => $taskMetrics['statuses']['in_progress'],
            'overdue_tasks' => $taskMetrics['overdue'],
            'days_reported' => (int) (clone $monthlyEntries)->distinct()->count('work_date'),
            'completion_rate' => $assignedTasks === 0
                ? 0.0
                : round(($completedTasks / $assignedTasks) * 100, 1),
        ];
    }

    /**
     * @return array{total: int, statuses: array<string, int>, overdue: int}
     */
    public function taskMetricsFor(User $user): array
    {
        $grouped = $this->assignedTasks($user)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statuses = collect(Task::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => (int) ($grouped[$status] ?? 0)])
            ->all();

        $overdue = $this->assignedTasks($user)
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        return [
            'total' => array_sum($statuses),
            'statuses' => $statuses,
            'overdue' => $overdue,
        ];
    }

    /**
     * @param  array{total: int, statuses: array<string, int>, overdue: int}|null  $taskMetrics
     * @return array<string, array<string, mixed>>
     */
    public function chartDataFor(User $user, ?array $taskMetrics = null): array
    {
        $taskMetrics ??= $this->taskMetricsFor($user);
        $taskStatuses = collect(Task::STATUSES)->reject(fn (string $status) => $status === 'cancelled');
        $monthStart = today()->startOfMonth();
        $monthEnd = today()->endOfMonth();

        $projectHours = WorkEntry::query()
            ->where('work_entries.user_id', $user->id)
            ->whereBetween('work_entries.work_date', [$monthStart, $monthEnd])
            ->join('projects', 'projects.id', '=', 'work_entries.project_id')
            ->selectRaw('projects.project_code, projects.title, SUM(work_entries.duration_minutes) as total_minutes')
            ->groupBy('projects.id', 'projects.project_code', 'projects.title')
            ->orderByDesc('total_minutes')
            ->get();

        $weekStart = CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY);
        $weekEnd = $weekStart->endOfWeek(CarbonImmutable::SUNDAY);
        $weeklyMinutes = WorkEntry::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$weekStart, $weekEnd])
            ->selectRaw('work_date, SUM(duration_minutes) as total_minutes')
            ->groupBy('work_date')
            ->get()
            ->mapWithKeys(fn (WorkEntry $entry) => [
                $entry->work_date->toDateString() => (int) $entry->total_minutes,
            ]);
        $weekDates = collect(range(0, 6))->map(fn (int $offset) => $weekStart->addDays($offset));

        return [
            'task_status' => [
                'labels' => $taskStatuses->map(fn (string $status) => Task::label($status))->values()->all(),
                'values' => $taskStatuses->map(fn (string $status) => $taskMetrics['statuses'][$status])->values()->all(),
                'total' => $taskMetrics['total'],
                'overdue' => $taskMetrics['overdue'],
            ],
            'hours_by_project' => [
                'labels' => $projectHours->map(fn ($project) => $project->project_code.' — '.$project->title)->all(),
                'values' => $projectHours->map(fn ($project) => round(((int) $project->total_minutes) / 60, 2))->all(),
                'total_minutes' => (int) $projectHours->sum('total_minutes'),
            ],
            'weekly_hours' => [
                'labels' => $weekDates->map(fn (CarbonImmutable $date) => $date->format('l'))->all(),
                'values' => $weekDates->map(fn (CarbonImmutable $date) => round(((int) ($weeklyMinutes[$date->toDateString()] ?? 0)) / 60, 2))->all(),
                'dates' => $weekDates->map(fn (CarbonImmutable $date) => $date->toDateString())->all(),
            ],
        ];
    }

    /**
     * @return Collection<int, Project>
     */
    public function activeProjectsFor(User $user): Collection
    {
        $projects = $this->activeProjects($user)
            ->with(['projectMembers' => fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('is_active', true)])
            ->withCount(['tasks as user_active_tasks_count' => fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('status', '!=', 'cancelled')
                ->whereHas('taskAssignees', fn (Builder $query) => $query
                    ->where('user_id', $user->id)
                    ->where('is_active', true))])
            ->orderBy('due_date')
            ->orderBy('title')
            ->get();

        $viewableProjectIds = $user->can('view projects')
            ? Project::query()->visibleTo($user)->whereKey($projects->modelKeys())->pluck('id')
            : collect();

        $projects->each(fn (Project $project) => $project->setAttribute(
            'dashboard_can_view',
            $viewableProjectIds->contains($project->id)
        ));

        return $projects;
    }

    /**
     * @return Collection<int, Task>
     */
    public function upcomingDeadlinesFor(User $user): Collection
    {
        $tasks = $this->assignedTasks($user)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereBetween('due_date', [
                today()->toDateString(),
                today()->addDays(self::UPCOMING_DEADLINE_DAYS)->toDateString(),
            ])
            ->with('project:id,project_code,title')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(self::UPCOMING_DEADLINE_LIMIT)
            ->get();

        $canViewTasks = $user->can('view tasks');
        $tasks->each(fn (Task $task) => $task->setAttribute('dashboard_can_view', $canViewTasks));

        return $tasks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentActivityFor(User $user): array
    {
        $workEntryActivities = WorkEntryActivity::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('workEntry', fn (Builder $query) => $query->where('user_id', $user->id));
            })
            ->with(['workEntry:id,user_id,entry_code', 'user:id,name'])
            ->latest()
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get();

        $taskActivities = TaskActivity::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('task.taskAssignees', fn (Builder $query) => $query
                        ->where('user_id', $user->id)
                        ->where('is_active', true));
            })
            ->with(['task:id,task_code,title,reviewer_id', 'user:id,name'])
            ->latest()
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get();

        $viewableTaskIds = collect();
        if ($user->can('view tasks')) {
            $activityTaskIds = $taskActivities->pluck('task_id');
            $viewableTaskIds = Task::query()
                ->visibleTo($user)
                ->whereKey($activityTaskIds)
                ->pluck('id')
                ->merge(Task::query()
                    ->whereKey($activityTaskIds)
                    ->where('reviewer_id', $user->id)
                    ->pluck('id'))
                ->unique();
        }

        return $workEntryActivities
            ->map(fn (WorkEntryActivity $activity) => [
                'type' => $activity->event,
                'title' => $activity->event_label,
                'description' => $activity->description,
                'code' => $activity->metadata['evidence_code'] ?? $activity->workEntry?->entry_code,
                'occurred_at' => $activity->created_at,
                'actor' => $activity->user?->name,
                'url' => $activity->workEntry?->user_id === $user->id
                    ? route('work-entries.show', $activity->work_entry_id)
                    : null,
            ])
            ->concat($taskActivities->map(fn (TaskActivity $activity) => [
                'type' => $activity->activity_type,
                'title' => $this->taskActivityTitle($activity->activity_type),
                'description' => $activity->message ?: $this->taskActivityDescription($activity),
                'code' => $activity->task?->task_code,
                'occurred_at' => $activity->created_at,
                'actor' => $activity->user?->name,
                'url' => $viewableTaskIds->contains($activity->task_id)
                    ? route('tasks.show', $activity->task_id)
                    : null,
            ]))
            ->sortByDesc('occurred_at')
            ->take(self::RECENT_ACTIVITY_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function alertsFor(User $user): array
    {
        $tasks = $this->assignedTasks($user)
            ->where(function (Builder $query) {
                $query->whereDate('due_date', '<=', today()->addDays(self::URGENT_DEADLINE_DAYS))
                    ->orWhereNotNull('returned_at')
                    ->orWhere('status', 'pending_review');
            })
            ->where('status', '!=', 'completed')
            ->orderByRaw('due_date is null, due_date asc')
            ->get(['id', 'task_code', 'title', 'status', 'due_date', 'returned_at']);

        $canViewTasks = $user->can('view tasks');

        return $tasks->map(function (Task $task) use ($canViewTasks) {
            $alert = match (true) {
                $task->is_overdue => [
                    'type' => 'overdue',
                    'severity' => 'danger',
                    'title' => 'Overdue Task',
                    'message' => "{$task->task_code} is overdue by ".(int) $task->due_date->diffInDays(today()).' '.str('day')->plural((int) $task->due_date->diffInDays(today())).'.',
                ],
                $task->returned_at !== null && $task->status === 'in_progress' => [
                    'type' => 'returned_for_correction',
                    'severity' => 'warning',
                    'title' => 'Returned for Correction',
                    'message' => "{$task->task_code} was returned for correction.",
                ],
                $task->due_date?->betweenIncluded(today(), today()->addDays(self::URGENT_DEADLINE_DAYS)) => [
                    'type' => 'due_soon',
                    'severity' => 'warning',
                    'title' => 'Deadline Due Soon',
                    'message' => $this->dueSoonMessage($task),
                ],
                $task->status === 'pending_review' => [
                    'type' => 'pending_review',
                    'severity' => 'info',
                    'title' => 'Pending Review',
                    'message' => "{$task->task_code} is awaiting supervisor review.",
                ],
                default => null,
            };

            return $alert ? [...$alert, 'code' => $task->task_code, 'url' => $canViewTasks ? route('tasks.show', $task) : null] : null;
        })
            ->filter()
            ->sortBy(fn (array $alert) => array_search($alert['severity'], ['danger', 'warning', 'info'], true))
            ->take(self::ALERT_LIMIT)
            ->values()
            ->all();
    }

    private function activeProjects(User $user): Builder
    {
        return Project::query()
            ->where('is_active', true)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where(function (Builder $query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('projectMembers', fn (Builder $query) => $query
                        ->where('user_id', $user->id)
                        ->where('is_active', true));
            });
    }

    private function assignedTasks(User $user): Builder
    {
        return Task::query()
            ->where('is_active', true)
            ->where('status', '!=', 'cancelled')
            ->whereHas('taskAssignees', fn (Builder $query) => $query
                ->where('user_id', $user->id)
                ->where('is_active', true));
    }

    private function taskActivityTitle(string $type): string
    {
        return match ($type) {
            'task_started' => 'Task Started',
            'progress_updated' => 'Task Progress Updated',
            'submitted_for_review' => 'Task Submitted for Review',
            'task_approved' => 'Task Approved',
            'task_returned' => 'Task Returned for Correction',
            'subtask_created' => 'Subtask Created',
            'subtask_updated' => 'Subtask Updated',
            'subtask_completed' => 'Subtask Completed',
            'subtask_reopened' => 'Subtask Reopened',
            default => str($type)->replace('_', ' ')->title()->toString(),
        };
    }

    private function taskActivityDescription(TaskActivity $activity): string
    {
        return match ($activity->activity_type) {
            'task_started' => "{$activity->task?->task_code} was started.",
            'progress_updated' => "{$activity->task?->task_code} progress changed from {$activity->old_progress}% to {$activity->new_progress}%.",
            'submitted_for_review' => "{$activity->task?->task_code} was submitted for supervisor review.",
            'task_approved' => "{$activity->task?->task_code} was approved and completed.",
            'task_returned' => "{$activity->task?->task_code} was returned for correction.",
            default => "{$activity->task?->task_code} was updated.",
        };
    }

    private function dueSoonMessage(Task $task): string
    {
        $days = (int) today()->diffInDays($task->due_date);

        return match ($days) {
            0 => "{$task->task_code} is due today.",
            1 => "{$task->task_code} is due tomorrow.",
            default => "{$task->task_code} is due in {$days} days.",
        };
    }
}

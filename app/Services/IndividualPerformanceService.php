<?php

namespace App\Services;

use App\Models\MonthlyReport;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;

class IndividualPerformanceService
{
    public function for(User $staff, int $month, int $year): array
    {
        $period = CarbonImmutable::create($year, $month, 1);
        $start = $period->startOfMonth();
        $end = $period->endOfMonth();
        $entries = WorkEntry::query()->where('user_id', $staff->id)
            ->whereBetween('work_date', [$start, $end])->with(['project:id,project_code,title', 'task:id,task_code,title'])
            ->orderBy('work_date')->get();
        $tasks = Task::query()->where('is_active', true)->where('status', '!=', 'cancelled')
            ->whereHas('taskAssignees', fn ($query) => $query->where('user_id', $staff->id)->where('is_active', true)
                ->whereBetween('assigned_at', [$start->startOfDay(), $end->endOfDay()]))
            ->with('project:id,project_code,title')->get();
        $reports = MonthlyReport::query()->where('user_id', $staff->id)
            ->with(['reviewer:id,name', 'activities.user:id,name'])
            ->orderByDesc('reporting_year')->orderByDesc('reporting_month')->limit(12)->get();

        $statusLabels = collect(Task::STATUSES)->reject(fn ($status) => $status === 'cancelled');
        $daily = $entries->groupBy(fn (WorkEntry $entry) => $entry->work_date->toDateString());
        $dates = collect(range(1, $period->daysInMonth))->map(fn ($day) => $period->day($day));
        $projectMinutes = $entries->groupBy('project_id')->map(fn ($group) => $group->sum('duration_minutes'))->sortDesc();

        return [
            'staff' => $staff->loadMissing(['roles', 'staffProfile.campus', 'staffProfile.library', 'staffProfile.position', 'staffProfile.supervisor']),
            'period' => $period,
            'entries' => $entries->sortByDesc('work_date')->take(10),
            'tasks' => $tasks->sortByDesc('due_date')->take(10),
            'reports' => $reports,
            'metrics' => [
                'minutes' => (int) $entries->sum('duration_minutes'),
                'days' => $entries->unique(fn ($entry) => $entry->work_date->toDateString())->count(),
                'assigned' => $tasks->count(),
                'completed' => $tasks->where('status', 'completed')->count(),
                'overdue' => $tasks->filter(fn ($task) => $task->due_date?->isBefore($end->addDay()->min(today())) && ! in_array($task->status, ['completed', 'cancelled'], true))->count(),
                'completion_rate' => $tasks->isEmpty() ? 0.0 : round($tasks->where('status', 'completed')->count() / $tasks->count() * 100, 1),
                'projects' => $entries->pluck('project_id')->filter()->unique()->count(),
            ],
            'charts' => [
                'daily_hours' => ['labels' => $dates->map->format('d M'), 'values' => $dates->map(fn ($date) => round(($daily->get($date->toDateString())?->sum('duration_minutes') ?? 0) / 60, 2))],
                'task_status' => ['labels' => $statusLabels->map(fn ($status) => Task::label($status))->values(), 'values' => $statusLabels->map(fn ($status) => $tasks->where('status', $status)->count())->values()],
                'projects' => ['labels' => $projectMinutes->keys()->map(fn ($id) => $entries->firstWhere('project_id', $id)?->project?->title ?? 'Unassigned'), 'values' => $projectMinutes->values()->map(fn ($minutes) => round($minutes / 60, 2))],
                'locations' => ['labels' => $entries->groupBy(fn ($entry) => $entry->work_location ?: 'Not specified')->keys(), 'values' => $entries->groupBy(fn ($entry) => $entry->work_location ?: 'Not specified')->map->count()->values()],
            ],
        ];
    }
}

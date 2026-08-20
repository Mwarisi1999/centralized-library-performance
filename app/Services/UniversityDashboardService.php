<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\CampusMonthlyReport;
use App\Models\CampusMonthlyReportActivity;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UniversityDashboardService
{
    public function __construct(private readonly CampusDashboardService $campusDashboard) {}

    public function dashboard(int $month, int $year): array
    {
        $period = CarbonImmutable::create($year, $month, 1);
        $campuses = Campus::query()->where('is_active', true)->withCount([
            'libraries as active_libraries_count' => fn (Builder $query) => $query->where('is_active', true),
        ])->orderBy('name')->get();
        $reports = CampusMonthlyReport::query()->with(['finalizer:id,name', 'campus:id,name'])
            ->whereIn('campus_id', $campuses->pluck('id'))
            ->where('reporting_month', $month)->where('reporting_year', $year)->get()->keyBy('campus_id');

        $campusRows = $campuses->map(function (Campus $campus) use ($month, $year, $reports) {
            $data = $this->campusDashboard->dashboardForCampus($campus, $month, $year);
            $summary = $data['summary'];
            $report = $reports->get($campus->id);

            return compact('campus', 'report', 'data') + [
                'libraries' => $campus->active_libraries_count,
                'total_staff' => $summary['total_staff'],
                'active_staff' => $summary['active_staff'],
                'minutes' => $summary['minutes'],
                'staff_reporting' => $summary['staff_reporting'],
                'assigned' => $summary['active_tasks'],
                'completed' => $summary['completed_tasks'],
                'in_progress' => $summary['in_progress_tasks'],
                'overdue' => $summary['overdue_tasks'],
                'completion_rate' => $summary['active_tasks'] === 0 ? 0.0 : round($summary['completed_tasks'] / $summary['active_tasks'] * 100, 1),
                'active_projects' => $summary['active_projects'],
            ];
        });

        $tasks = $campusRows->pluck('data')->pluck('tasks')->collapse()->unique('id')->values();
        $projects = $campusRows->pluck('data')->pluck('projects')->collapse()->unique('id')->values();
        $staffRows = $campusRows->flatMap(fn (array $row) => $row['data']['staffRows']->map(fn (array $staff) => $staff + ['campus' => $row['campus']]))->values();
        $summary = [
            'total_campuses' => $campuses->count(),
            'total_libraries' => $campuses->sum('active_libraries_count'),
            'total_staff' => $campusRows->sum('total_staff'),
            'active_staff' => $campusRows->sum('active_staff'),
            'active_projects' => $projects->count(),
            'active_tasks' => $tasks->count(),
            'completed_tasks' => $tasks->where('status', 'completed')->count(),
            'in_progress_tasks' => $tasks->where('status', 'in_progress')->count(),
            'overdue_tasks' => $campusRows->pluck('data')->pluck('tasks')->collapse()->filter(fn (Task $task) => $this->overdue($task, $period))->unique('id')->count(),
            'minutes' => $campusRows->sum('minutes'),
            'staff_reporting' => $campusRows->sum('staff_reporting'),
            'reports_finalized' => $reports->count(),
            'reports_outstanding' => $campuses->count() - $reports->count(),
        ];

        return compact('period', 'campusRows', 'staffRows', 'summary') + [
            'projectRows' => $this->projectRows($projects, $tasks, $staffRows, $period),
            'reportRows' => $this->reportRows($campuses, $reports),
            'charts' => [
                'hours_by_campus' => ['labels' => $campusRows->pluck('campus.name'), 'values' => $campusRows->pluck('minutes')->map(fn ($value) => round($value / 60, 2))],
                'task_status' => ['labels' => collect(Task::STATUSES)->reject(fn ($status) => $status === 'cancelled')->map(fn ($status) => Task::label($status))->values(), 'values' => collect(Task::STATUSES)->reject(fn ($status) => $status === 'cancelled')->map(fn ($status) => $tasks->where('status', $status)->count())->values()],
                'completion_rates' => ['labels' => $campusRows->pluck('campus.name'), 'values' => $campusRows->pluck('completion_rate')],
                'report_status' => ['labels' => ['Finalized', 'Not Finalized'], 'values' => [$reports->count(), $campuses->count() - $reports->count()]],
                'project_progress' => ['labels' => $projects->pluck('title'), 'values' => $projects->pluck('progress_percentage')->map(fn ($value) => round((float) $value, 1))],
            ],
            'recentActivity' => $this->recentActivity(),
        ];
    }

    public function campusDetail(Campus $campus, int $month, int $year): array
    {
        $data = $this->campusDashboard->dashboardForCampus($campus, $month, $year);
        $report = CampusMonthlyReport::query()->with('finalizer:id,name')->where('campus_id', $campus->id)
            ->where('reporting_month', $month)->where('reporting_year', $year)->first();

        return $data + ['report' => $report, 'libraries' => $campus->libraries()->where('is_active', true)->orderBy('name')->get()];
    }

    private function projectRows(Collection $projects, Collection $tasks, Collection $staffRows, CarbonImmutable $period): Collection
    {
        $staffIds = $staffRows->pluck('user.id');

        return $projects->map(function (Project $project) use ($tasks, $staffIds, $period) {
            $projectTasks = $tasks->where('project_id', $project->id);

            return [
                'project' => $project->loadMissing('campuses:id,name'),
                'staff' => TaskAssignee::query()->whereIn('task_id', $projectTasks->pluck('id'))->whereIn('user_id', $staffIds)->where('is_active', true)->distinct('user_id')->count('user_id'),
                'tasks' => $projectTasks->count(),
                'completed' => $projectTasks->where('status', 'completed')->count(),
                'in_progress' => $projectTasks->where('status', 'in_progress')->count(),
                'overdue' => $projectTasks->filter(fn (Task $task) => $this->overdue($task, $period))->count(),
            ];
        })->sortBy(fn (array $row) => $row['project']->title)->values();
    }

    private function reportRows(Collection $campuses, Collection $reports): Collection
    {
        return $campuses->map(function (Campus $campus) use ($reports) {
            $librarian = User::role('Campus Librarian')->where('account_status', 'active')->whereHas('staffProfile', fn (Builder $query) => $query->where('campus_id', $campus->id)->where('status', 'active'))->orderBy('name')->first();

            return ['campus' => $campus, 'librarian' => $librarian, 'report' => $reports->get($campus->id)];
        });
    }

    private function overdue(Task $task, CarbonImmutable $period): bool
    {
        $cutoff = $period->endOfMonth()->addDay()->min(CarbonImmutable::today());

        return $task->due_date?->lt($cutoff) && ! in_array($task->status, ['completed', 'cancelled'], true);
    }

    private function recentActivity(): Collection
    {
        return CampusMonthlyReportActivity::query()->with('user:id,name')->latest()->limit(10)->get()->map(fn ($activity) => [
            'title' => str($activity->event)->replace('_', ' ')->title()->toString(),
            'description' => $activity->description,
            'user' => $activity->user?->name,
            'at' => $activity->created_at,
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportActivity;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Models\WorkEntry;
use App\Models\WorkEntryActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampusDashboardService
{
    public const DUE_SOON_DAYS = 14;

    public function campusFor(User $user)
    {
        $profile = $user->staffProfile()->with('campus')->first();

        return $profile?->status === 'active' ? $profile->campus : null;
    }

    public function dashboardFor(User $viewer, int $month, int $year): array
    {
        $campus = $this->campusFor($viewer);

        return $this->dashboardForCampus($campus, $month, $year);
    }

    public function dashboardForCampus(?Campus $campus, int $month, int $year): array
    {
        $period = CarbonImmutable::create($year, $month, 1);

        if (! $campus || ! $campus->is_active) {
            return $this->emptyDashboard($campus, $period);
        }

        $staff = User::query()->with(['staffProfile.position', 'staffProfile.library'])
            ->whereHas('staffProfile', fn (Builder $q) => $q->where('campus_id', $campus->id))
            ->orderBy('name')->get();
        $staffIds = $staff->pluck('id');
        $activeStaffIds = $staff->filter(fn (User $user) => $user->account_status === 'active' && $user->staffProfile?->status === 'active')->pluck('id');
        $start = $period->startOfMonth();
        $end = $period->endOfMonth();
        $cutoff = $end->addDay()->min(CarbonImmutable::today());

        $work = WorkEntry::query()->whereIn('user_id', $staffIds)->whereBetween('work_date', [$start, $end])
            ->selectRaw('user_id, SUM(duration_minutes) as minutes, COUNT(DISTINCT work_date) as days')
            ->groupBy('user_id')->get()->keyBy('user_id');

        $tasks = $this->tasksForPeriod($staffIds, $start, $end)->with(['project:id,project_code,title', 'assignees' => fn ($q) => $q->whereIn('users.id', $staffIds)])->get();
        $reports = MonthlyReport::query()->whereIn('user_id', $staffIds)
            ->where('reporting_month', $month)->where('reporting_year', $year)->get()->keyBy('user_id');

        $staffRows = $staff->map(function (User $user) use ($work, $tasks, $reports, $cutoff) {
            $userTasks = $tasks->filter(fn (Task $task) => $task->assignees->contains('id', $user->id));
            $completed = $userTasks->where('status', 'completed')->count();

            return [
                'user' => $user,
                'minutes' => (int) ($work->get($user->id)?->minutes ?? 0),
                'days' => (int) ($work->get($user->id)?->days ?? 0),
                'assigned' => $userTasks->count(),
                'completed' => $completed,
                'in_progress' => $userTasks->where('status', 'in_progress')->count(),
                'overdue' => $userTasks->filter(fn (Task $task) => $task->due_date?->lt($cutoff) && ! in_array($task->status, ['completed', 'cancelled'], true))->count(),
                'completion_rate' => $userTasks->isEmpty() ? 0.0 : round($completed / $userTasks->count() * 100, 1),
                'report_status' => $reports->get($user->id)?->status,
            ];
        });

        $projects = $this->projectsForCampus($campus->id, $staffIds)->with(['tasks' => fn ($q) => $q
            ->where('is_active', true)->where('status', '!=', 'cancelled')
            ->whereHas('taskAssignees', fn ($a) => $a->whereIn('user_id', $staffIds)->where('is_active', true)),
            'tasks.taskAssignees' => fn ($q) => $q->whereIn('user_id', $staffIds)->where('is_active', true),
        ])->get();

        $reportCounts = collect(MonthlyReport::STATUSES)->mapWithKeys(fn ($status) => [$status => $reports->where('status', $status)->count()]);
        $summary = [
            'total_staff' => $staff->count(), 'active_staff' => $activeStaffIds->count(),
            'active_projects' => $projects->count(), 'active_tasks' => $tasks->count(),
            'completed_tasks' => $tasks->where('status', 'completed')->count(),
            'in_progress_tasks' => $tasks->where('status', 'in_progress')->count(),
            'overdue_tasks' => $tasks->filter(fn (Task $task) => $task->due_date?->lt($cutoff) && ! in_array($task->status, ['completed', 'cancelled'], true))->count(),
            'minutes' => (int) $work->sum('minutes'), 'staff_reporting' => $work->filter(fn ($row) => $row->minutes > 0)->count(),
            'reports' => $reportCounts, 'no_report' => $staff->count() - $reports->count(),
        ];

        $hoursByProject = WorkEntry::query()->join('projects', 'projects.id', '=', 'work_entries.project_id')
            ->whereIn('work_entries.user_id', $staffIds)->whereBetween('work_entries.work_date', [$start, $end])
            ->groupBy('projects.id', 'projects.title')->orderByDesc(DB::raw('SUM(work_entries.duration_minutes)'))->limit(8)
            ->get(['projects.title', DB::raw('SUM(work_entries.duration_minutes) as minutes')]);

        return compact('campus', 'period', 'staffRows', 'projects', 'summary', 'tasks') + [
            'taskSections' => [
                'in_progress' => $tasks->where('status', 'in_progress')->take(8),
                'pending_review' => $tasks->where('status', 'pending_review')->take(8),
                'due_soon' => $tasks->filter(fn (Task $t) => $t->due_date?->between(today(), today()->addDays(self::DUE_SOON_DAYS)) && ! in_array($t->status, ['completed', 'cancelled'], true))->take(8),
                'overdue' => $tasks->filter(fn (Task $t) => $t->due_date?->lt($cutoff) && ! in_array($t->status, ['completed', 'cancelled'], true))->take(8),
            ],
            'charts' => [
                'task_status' => ['labels' => collect(Task::STATUSES)->reject(fn ($s) => $s === 'cancelled')->map(fn ($s) => Task::label($s))->values(), 'values' => collect(Task::STATUSES)->reject(fn ($s) => $s === 'cancelled')->map(fn ($s) => $tasks->where('status', $s)->count())->values()],
                'hours_by_staff' => ['labels' => $staffRows->pluck('user.name'), 'values' => $staffRows->pluck('minutes')->map(fn ($v) => round($v / 60, 2))],
                'hours_by_project' => ['labels' => $hoursByProject->pluck('title'), 'values' => $hoursByProject->pluck('minutes')->map(fn ($v) => round($v / 60, 2))],
            ],
            'recentActivity' => $this->recentActivity($staffIds),
        ];
    }

    public function staffDetailFor(User $viewer, User $staff, int $month, int $year, IndividualMonthlyReportService $reports): array
    {
        $campus = $this->campusFor($viewer);
        abort_unless($campus && $campus->is_active && $staff->staffProfile?->campus_id === $campus->id, 403);
        $period = CarbonImmutable::create($year, $month, 1);

        return [
            'campus' => $campus,
            'staff' => $staff->loadMissing(['staffProfile.position', 'staffProfile.library', 'staffProfile.supervisor']),
            'foundation' => $reports->foundationFor($staff, $month, $year),
            'recentEntries' => WorkEntry::query()->with(['project:id,project_code,title', 'task:id,task_code,title'])
                ->where('user_id', $staff->id)->whereBetween('work_date', [$period->startOfMonth(), $period->endOfMonth()])
                ->latest('work_date')->limit(10)->get(),
            'assignedTasks' => Task::query()->with('project:id,project_code,title')->where('is_active', true)->where('status', '!=', 'cancelled')
                ->whereHas('taskAssignees', fn ($q) => $q->where('user_id', $staff->id)->where('is_active', true))->latest()->limit(10)->get(),
            'period' => $period,
        ];
    }

    public function tasksForPeriod(Collection $staffIds, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return Task::query()->where('is_active', true)->where('status', '!=', 'cancelled')
            ->whereHas('taskAssignees', fn ($q) => $q->whereIn('user_id', $staffIds)->where('is_active', true)
                ->whereBetween(DB::raw('COALESCE(task_assignees.assigned_at, task_assignees.created_at)'), [$start->startOfDay(), $end->endOfDay()]));
    }

    public function projectsForCampus(int $campusId, Collection $staffIds): Builder
    {
        return Project::query()->where('is_active', true)->whereNotIn('status', ['completed', 'cancelled'])
            ->where(function ($q) use ($campusId, $staffIds) {
                $q->where('scope', 'university_wide')->orWhereHas('campuses', fn ($c) => $c->whereKey($campusId))
                    ->orWhereHas('projectMembers', fn ($m) => $m->whereIn('user_id', $staffIds)->where('is_active', true))
                    ->orWhereHas('tasks.taskAssignees', fn ($a) => $a->whereIn('user_id', $staffIds)->where('is_active', true));
            })->orderBy('due_date');
    }

    private function recentActivity(Collection $staffIds): Collection
    {
        $work = WorkEntryActivity::with('user:id,name')->whereHas('workEntry', fn ($q) => $q->whereIn('user_id', $staffIds))->latest()->limit(10)->get()
            ->map(fn ($a) => ['title' => $a->event_label, 'description' => $a->description, 'user' => $a->user?->name, 'at' => $a->created_at]);
        $task = TaskActivity::with('user:id,name')->whereHas('task.taskAssignees', fn ($q) => $q->whereIn('user_id', $staffIds)->where('is_active', true))->latest()->limit(10)->get()
            ->map(fn ($a) => ['title' => str($a->activity_type)->replace('_', ' ')->title(), 'description' => $a->message, 'user' => $a->user?->name, 'at' => $a->created_at]);
        $reports = MonthlyReportActivity::with('user:id,name')->whereHas('monthlyReport', fn ($q) => $q->whereIn('user_id', $staffIds))->latest()->limit(10)->get()
            ->map(fn ($a) => ['title' => $a->event_label, 'description' => $a->description, 'user' => $a->user?->name, 'at' => $a->created_at]);

        return $work->concat($task)->concat($reports)->sortByDesc('at')->take(10)->values();
    }

    private function emptyDashboard($campus, CarbonImmutable $period): array
    {
        return ['campus' => $campus, 'period' => $period, 'staffRows' => collect(), 'projects' => collect(), 'tasks' => collect(),
            'summary' => ['total_staff' => 0, 'active_staff' => 0, 'active_projects' => 0, 'active_tasks' => 0, 'completed_tasks' => 0, 'in_progress_tasks' => 0, 'overdue_tasks' => 0, 'minutes' => 0, 'staff_reporting' => 0, 'reports' => collect(), 'no_report' => 0],
            'taskSections' => ['in_progress' => collect(), 'pending_review' => collect(), 'due_soon' => collect(), 'overdue' => collect()],
            'charts' => ['task_status' => ['labels' => [], 'values' => []], 'hours_by_staff' => ['labels' => [], 'values' => []], 'hours_by_project' => ['labels' => [], 'values' => []]], 'recentActivity' => collect()];
    }
}

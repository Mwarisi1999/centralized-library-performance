<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\CampusMonthlyReport;
use App\Models\MonthlyReport;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampusMonthlyReportService
{
    public function reportFor(User $viewer, int $month, int $year): array
    {
        $campus = $this->campusFor($viewer);
        abort_unless($campus, 403);

        $persisted = CampusMonthlyReport::query()
            ->with('finalizer:id,name')
            ->where('campus_id', $campus->id)
            ->where('reporting_month', $month)
            ->where('reporting_year', $year)
            ->first();

        if ($persisted) {
            return ['report' => $persisted, 'data' => $persisted->snapshot, 'isFrozen' => true];
        }

        return ['report' => null, 'data' => $this->liveData($viewer, $campus, $month, $year), 'isFrozen' => false];
    }

    public function liveData(User $viewer, Campus $campus, int $month, int $year): array
    {
        $period = CarbonImmutable::create($year, $month, 1);
        $start = $period->startOfMonth();
        $end = $period->endOfMonth();
        $cutoff = $end->addDay()->min(CarbonImmutable::today());

        $staff = User::query()
            ->with(['staffProfile.position:id,name', 'staffProfile.library:id,name'])
            ->where('account_status', 'active')
            ->whereHas('staffProfile', fn (Builder $query) => $query
                ->where('campus_id', $campus->id)->where('status', 'active'))
            ->orderBy('name')->get();
        $staffIds = $staff->pluck('id');

        $work = WorkEntry::query()->whereIn('user_id', $staffIds)->whereBetween('work_date', [$start, $end])
            ->selectRaw('user_id, SUM(duration_minutes) as minutes, COUNT(DISTINCT work_date) as days')
            ->groupBy('user_id')->get()->keyBy('user_id');

        $tasks = $this->tasksForPeriod($staffIds, $start, $end)
            ->with(['project:id,project_code,title,status,progress_percentage', 'assignees' => fn ($query) => $query
                ->whereIn('users.id', $staffIds)->wherePivot('is_active', true)->select('users.id', 'users.name')])
            ->get();

        $monthlyReports = MonthlyReport::query()->whereIn('user_id', $staffIds)
            ->where('reporting_month', $month)->where('reporting_year', $year)->get()->keyBy('user_id');

        $staffRows = $staff->map(function (User $user) use ($work, $tasks, $monthlyReports, $cutoff) {
            $assigned = $tasks->filter(fn (Task $task) => $task->assignees->contains('id', $user->id));
            $completed = $assigned->where('status', 'completed')->count();

            return [
                'name' => $user->name,
                'position' => $user->staffProfile?->position?->name,
                'library' => $user->staffProfile?->library?->name,
                'hours' => WorkEntry::formatMinutes((int) ($work->get($user->id)?->minutes ?? 0)),
                'minutes' => (int) ($work->get($user->id)?->minutes ?? 0),
                'days_reported' => (int) ($work->get($user->id)?->days ?? 0),
                'tasks_assigned' => $assigned->count(),
                'completed' => $completed,
                'in_progress' => $assigned->where('status', 'in_progress')->count(),
                'overdue' => $assigned->filter(fn (Task $task) => $this->isOverdue($task, $cutoff))->count(),
                'completion_rate' => $assigned->isEmpty() ? 0.0 : round($completed / $assigned->count() * 100, 1),
                'report_status' => $monthlyReports->get($user->id)?->status ?? 'no_report',
            ];
        })->values();

        $projects = $tasks->groupBy('project_id')->map(function (Collection $projectTasks) use ($cutoff) {
            $project = $projectTasks->first()->project;

            return [
                'code' => $project?->project_code,
                'title' => $project?->title,
                'status' => $project?->status,
                'progress' => round((float) ($project?->progress_percentage ?? 0), 1),
                'staff' => $projectTasks->flatMap->assignees->unique('id')->pluck('name')->values()->all(),
                'task_count' => $projectTasks->count(),
                'completed' => $projectTasks->where('status', 'completed')->count(),
                'in_progress' => $projectTasks->where('status', 'in_progress')->count(),
                'overdue' => $projectTasks->filter(fn (Task $task) => $this->isOverdue($task, $cutoff))->count(),
            ];
        })->values();

        $narratives = WorkEntry::query()->with('user:id,name')->whereIn('user_id', $staffIds)
            ->whereBetween('work_date', [$start, $end])->orderBy('work_date')->orderBy('id')
            ->get(['id', 'user_id', 'work_date', 'output_deliverable', 'challenge_encountered', 'corrective_action', 'support_required', 'planned_next_activity']);

        $completed = $tasks->where('status', 'completed')->count();
        $assigned = $tasks->count();
        $statusCounts = collect(MonthlyReport::STATUSES)->mapWithKeys(
            fn (string $status) => [$status => $monthlyReports->where('status', $status)->count()]
        )->put('no_report', $staff->count() - $monthlyReports->count());

        return [
            'identity' => [
                'campus' => $campus->name,
                'period' => $period->format('F Y'),
                'month' => $month,
                'year' => $year,
                'generated_by' => $viewer->name,
                'campus_librarian' => $viewer->name,
                'status' => 'draft',
            ],
            'campus' => [
                'name' => $campus->name,
                'campus_librarian' => $viewer->name,
                'libraries' => $campus->libraries()->where('is_active', true)->count(),
                'total_staff' => $staff->count(),
                'active_staff' => $staff->count(),
            ],
            'performance' => [
                'total_hours' => WorkEntry::formatMinutes((int) $work->sum('minutes')),
                'total_minutes' => (int) $work->sum('minutes'),
                'staff_reporting' => $work->filter(fn ($row) => (int) $row->minutes > 0)->count(),
                'tasks_assigned' => $assigned,
                'tasks_completed' => $completed,
                'tasks_in_progress' => $tasks->where('status', 'in_progress')->count(),
                'pending_review' => $tasks->where('status', 'pending_review')->count(),
                'overdue' => $tasks->filter(fn (Task $task) => $this->isOverdue($task, $cutoff))->count(),
                'completion_rate' => $assigned === 0 ? 0.0 : round($completed / $assigned * 100, 1),
                'active_projects' => $projects->reject(fn ($row) => in_array($row['status'], ['completed', 'cancelled'], true))->count(),
                'average_project_progress' => $projects->isEmpty() ? 0.0 : round((float) $projects->avg('progress'), 1),
            ],
            'staff_rows' => $staffRows->all(),
            'project_rows' => $projects->all(),
            'task_status' => collect(Task::STATUSES)->mapWithKeys(fn (string $status) => [$status => $tasks->where('status', $status)->count()])->all(),
            'report_status' => $statusCounts->all(),
            'narrative' => [
                'achievements' => $this->narrative($narratives, 'output_deliverable'),
                'challenges' => $this->narrative($narratives, 'challenge_encountered'),
                'corrective_actions' => $this->narrative($narratives, 'corrective_action'),
                'support_required' => $this->narrative($narratives, 'support_required'),
                'planned_activities' => $this->narrative($narratives, 'planned_next_activity'),
            ],
        ];
    }

    public function campusFor(User $user): ?Campus
    {
        $profile = $user->staffProfile()->with('campus')->first();

        return $profile?->status === 'active' && $profile->campus?->is_active ? $profile->campus : null;
    }

    private function tasksForPeriod(Collection $staffIds, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return Task::query()->where('is_active', true)->where('status', '!=', 'cancelled')
            ->whereHas('taskAssignees', fn (Builder $query) => $query->whereIn('user_id', $staffIds)
                ->where('is_active', true)->whereBetween(DB::raw('COALESCE(assigned_at, created_at)'), [$start->startOfDay(), $end->endOfDay()]));
    }

    private function isOverdue(Task $task, CarbonImmutable $cutoff): bool
    {
        return $task->due_date?->lt($cutoff) && ! in_array($task->status, ['completed', 'cancelled'], true);
    }

    private function narrative(Collection $entries, string $field): array
    {
        return $entries->filter(fn (WorkEntry $entry) => is_string($entry->{$field}) && trim($entry->{$field}) !== '')
            ->map(fn (WorkEntry $entry) => ['text' => trim($entry->{$field}), 'staff' => $entry->user?->name])
            ->unique('text')->values()->all();
    }
}

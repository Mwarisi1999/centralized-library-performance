<?php

namespace App\Services;

use App\Models\MonthlyReport;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IndividualMonthlyReportService
{
    /**
     * @return array<string, mixed>
     */
    public function foundationFor(User $user, int $month, int $year): array
    {
        $user->loadMissing([
            'staffProfile.position',
            'staffProfile.campus',
            'staffProfile.library',
            'staffProfile.supervisor',
        ]);

        $period = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $periodEnd = $period->endOfMonth();
        $report = MonthlyReport::query()
            ->with(['reviewer:id,name', 'submitter:id,name', 'activities.user:id,name'])
            ->where('user_id', $user->id)
            ->where('reporting_month', $month)
            ->where('reporting_year', $year)
            ->first();
        $profile = $user->staffProfile;
        $workEntries = WorkEntry::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$period, $periodEnd]);
        $narratives = (clone $workEntries)->get([
            'output_deliverable',
            'challenge_encountered',
            'corrective_action',
            'support_required',
            'planned_next_activity',
        ]);
        $taskMetrics = $this->taskMetricsForPeriod($user, $period, $periodEnd);

        $data = [
            'report' => $report,
            'status' => $report?->status ?? MonthlyReport::STATUS_DRAFT,
            'period' => [
                'month' => $month,
                'year' => $year,
                'label' => $period->format('F Y'),
            ],
            'staff' => [
                'name' => $user->name,
                'position' => $profile?->position?->name,
                'campus' => $profile?->campus?->name,
                'library' => $profile?->library?->name,
                'supervisor' => $profile?->supervisor?->name,
            ],
            'performance' => [
                'total_hours' => WorkEntry::formatMinutes((int) (clone $workEntries)->sum('duration_minutes')),
                'days_reported' => (int) (clone $workEntries)->distinct()->count('work_date'),
                'tasks_assigned' => $taskMetrics['assigned'],
                'tasks_completed' => $taskMetrics['completed'],
                'pending_tasks' => $taskMetrics['pending'],
                'overdue_tasks' => $taskMetrics['overdue'],
                'completion_rate' => $taskMetrics['assigned'] === 0
                    ? 0.0
                    : round(($taskMetrics['completed'] / $taskMetrics['assigned']) * 100, 1),
                'project_performance' => $taskMetrics['average_progress'],
            ],
            'narrative' => [
                'key_achievements' => $this->meaningfulUniqueValues($narratives->pluck('output_deliverable')),
                'challenges' => $this->meaningfulUniqueValues($narratives->pluck('challenge_encountered')),
                'corrective_actions' => $this->meaningfulUniqueValues($narratives->pluck('corrective_action')),
                'support_required' => $this->meaningfulUniqueValues($narratives->pluck('support_required')),
                'planned_activities_next_month' => $this->meaningfulUniqueValues($narratives->pluck('planned_next_activity')),
            ],
        ];

        if ($report?->submitted_snapshot
            && in_array($report->status, [MonthlyReport::STATUS_PENDING_REVIEW, MonthlyReport::STATUS_APPROVED], true)) {
            $data['staff'] = $report->submitted_snapshot['staff'];
            $data['performance'] = $report->submitted_snapshot['performance'];
            $data['narrative'] = $report->submitted_snapshot['narrative'];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotFor(User $user, int $month, int $year): array
    {
        $data = $this->foundationFor($user, $month, $year);

        return [
            'period' => $data['period'],
            'staff' => $data['staff'],
            'performance' => $data['performance'],
            'narrative' => $data['narrative'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function meaningfulUniqueValues(Collection $values): array
    {
        return $values
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{assigned: int, completed: int, pending: int, overdue: int, average_progress: float}
     */
    private function taskMetricsForPeriod(User $user, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $pendingStatuses = collect(Task::STATUSES)
            ->reject(fn (string $status) => in_array($status, ['completed', 'cancelled'], true))
            ->values()
            ->all();
        $overdueCutoff = $periodEnd->addDay()->min(CarbonImmutable::today());
        $tasks = $this->assignedTasksForPeriod($user, $periodStart, $periodEnd)
            ->selectRaw('COUNT(*) as assigned')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw(
                'SUM(CASE WHEN status IN ('.collect($pendingStatuses)->map(fn () => '?')->join(',').') THEN 1 ELSE 0 END) as pending',
                $pendingStatuses
            )
            ->selectRaw("SUM(CASE WHEN due_date < ? AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue", [$overdueCutoff->toDateString()])
            ->selectRaw('AVG(progress_percentage) as average_progress')
            ->first();

        return [
            'assigned' => (int) ($tasks?->assigned ?? 0),
            'completed' => (int) ($tasks?->completed ?? 0),
            'pending' => (int) ($tasks?->pending ?? 0),
            'overdue' => (int) ($tasks?->overdue ?? 0),
            'average_progress' => round((float) ($tasks?->average_progress ?? 0), 1),
        ];
    }

    private function assignedTasksForPeriod(User $user, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Builder
    {
        return Task::query()
            ->where('is_active', true)
            ->where('status', '!=', 'cancelled')
            ->whereHas('taskAssignees', fn (Builder $query) => $query
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->whereDate(DB::raw('COALESCE(task_assignees.assigned_at, task_assignees.created_at)'), '>=', $periodStart->toDateString())
                ->whereDate(DB::raw('COALESCE(task_assignees.assigned_at, task_assignees.created_at)'), '<=', $periodEnd->toDateString()));
    }
}

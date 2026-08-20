<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkEntryRequest;
use App\Http\Requests\TimesheetFilterRequest;
use App\Http\Requests\UpdateWorkEntryRequest;
use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use App\Services\WorkEntryCodeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorkEntryController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', WorkEntry::class);

        return view('my-work.index', [
            'entries' => $request->user()->workEntries()
                ->with(['project', 'task', 'subtask'])
                ->orderByDesc('work_date')->orderByDesc('start_time')->orderByDesc('id')
                ->paginate(15),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', WorkEntry::class);
        $context = $this->workContextFor($request->user());

        return view('work-entries.create', $context);
    }

    public function edit(Request $request, WorkEntry $workEntry)
    {
        Gate::authorize('update', $workEntry);

        return view('work-entries.edit', [
            'workEntry' => $workEntry,
            ...$this->workContextFor($request->user()),
        ]);
    }

    public function update(UpdateWorkEntryRequest $request, WorkEntry $workEntry)
    {
        $validated = $request->validated();
        $start = Carbon::createFromFormat('H:i', $validated['start_time']);
        $end = Carbon::createFromFormat('H:i', $validated['end_time']);
        $durationMinutes = intdiv($end->getTimestamp() - $start->getTimestamp(), 60);

        DB::transaction(function () use ($request, $workEntry, $validated, $durationMinutes) {
            $report = MonthlyReport::query()
                ->where('user_id', $workEntry->user_id)
                ->where('reporting_month', $workEntry->work_date->month)
                ->where('reporting_year', $workEntry->work_date->year)
                ->lockForUpdate()
                ->first();
            $workEntry = WorkEntry::query()->lockForUpdate()->findOrFail($workEntry->id);
            Gate::authorize('update', $workEntry);

            abort_unless($report?->status === MonthlyReport::STATUS_RETURNED_FOR_CORRECTION, 403);
            $workEntry->update([...$validated, 'duration_minutes' => $durationMinutes]);
            $workEntry->activities()->create([
                'user_id' => $request->user()->id,
                'event' => 'work_entry_updated',
                'description' => "Daily work entry {$workEntry->entry_code} was updated during report correction.",
            ]);
        });

        return redirect()->route('work-entries.show', $workEntry)->with('success', 'Daily work entry updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function workContextFor(User $user): array
    {
        $visibleProjectIds = Project::query()->visibleTo($user)->where('is_active', true)->select('id');
        $tasks = Task::query()
            ->whereIn('project_id', $visibleProjectIds)
            ->where('is_active', true)->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($user) {
                $query->whereHas('taskAssignees', fn ($query) => $query->where('user_id', $user->id)->where('is_active', true))
                    ->orWhereHas('subtasks', fn ($query) => $query->where('assigned_to', $user->id)->where('is_active', true)->where('status', '!=', 'cancelled'));
            })
            ->with([
                'project',
                'taskAssignees' => fn ($query) => $query->where('user_id', $user->id)->where('is_active', true),
                'subtasks' => fn ($query) => $query->where('is_active', true)->where('status', '!=', 'cancelled')->orderBy('sort_order')->orderBy('title'),
            ])->orderBy('title')->get();

        $tasks->each(function (Task $task) use ($user) {
            $task->setRelation('subtasks', $task->subtasks->where('assigned_to', $user->id)->values());
        });

        return [
            'projects' => $tasks->pluck('project')->unique('id')->sortBy('title')->values(),
            'tasks' => $tasks,
        ];
    }

    public function timesheet(TimesheetFilterRequest $request)
    {
        $filters = $request->validated();
        $userId = $request->user()->id;
        $filtered = WorkEntry::query()->where('user_id', $userId)
            ->whereDate('work_date', '>=', $filters['date_from'])
            ->whereDate('work_date', '<=', $filters['date_to'])
            ->when($filters['project_id'] ?? null, fn ($query, $id) => $query->where('project_id', $id))
            ->when($filters['task_id'] ?? null, fn ($query, $id) => $query->where('task_id', $id))
            ->when($filters['subtask_id'] ?? null, fn ($query, $id) => $query->where('subtask_id', $id));

        $totals = [
            'entries' => (clone $filtered)->count(),
            'minutes' => (int) (clone $filtered)->sum('duration_minutes'),
            'projects' => (clone $filtered)->distinct()->count('project_id'),
            'tasks' => (clone $filtered)->distinct()->count('task_id'),
            'subtasks' => (clone $filtered)->whereNotNull('subtask_id')->distinct()->count('subtask_id'),
        ];

        return view('my-work.timesheet', [
            'entries' => (clone $filtered)->with(['project', 'task', 'subtask'])
                ->orderByDesc('work_date')->orderByDesc('start_time')->orderByDesc('id')
                ->paginate(15)->withQueryString(),
            'filters' => $filters,
            'totals' => $totals,
            'projects' => Project::query()->whereHas('workEntries', fn ($query) => $query->where('user_id', $userId))->orderBy('title')->get(),
            'tasks' => Task::query()->whereHas('workEntries', fn ($query) => $query->where('user_id', $userId))
                ->when($filters['project_id'] ?? null, fn ($query, $id) => $query->where('project_id', $id))->orderBy('title')->get(),
            'subtasks' => Subtask::query()->whereHas('workEntries', fn ($query) => $query->where('user_id', $userId))
                ->when($filters['task_id'] ?? null, fn ($query, $id) => $query->where('task_id', $id))->orderBy('title')->get(),
        ]);
    }

    public function store(StoreWorkEntryRequest $request, WorkEntryCodeService $codes)
    {
        $validated = $request->validated();
        $start = Carbon::createFromFormat('H:i', $validated['start_time']);
        $end = Carbon::createFromFormat('H:i', $validated['end_time']);
        $durationMinutes = intdiv($end->getTimestamp() - $start->getTimestamp(), 60);

        $codes->withNextCode(fn ($entryCode) => DB::transaction(fn () => WorkEntry::create([
            ...$validated,
            'entry_code' => $entryCode,
            'user_id' => $request->user()->id,
            'duration_minutes' => $durationMinutes,
        ])));

        return redirect()->route('my-work.index')->with('success', 'Daily work entry recorded successfully.');
    }

    public function show(WorkEntry $workEntry)
    {
        Gate::authorize('view', $workEntry);
        $workEntry->load([
            'user', 'project', 'task', 'subtask',
            'evidences' => fn ($query) => $query->latest(),
            'activities' => fn ($query) => $query->with('user')->latest(),
        ]);

        return view('work-entries.show', [
            'workEntry' => $workEntry,
            'periodReportStatus' => MonthlyReport::query()
                ->where('user_id', $workEntry->user_id)
                ->where('reporting_month', $workEntry->work_date->month)
                ->where('reporting_year', $workEntry->work_date->year)
                ->value('status'),
        ]);
    }
}

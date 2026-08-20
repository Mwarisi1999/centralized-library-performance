<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveTaskRequest;
use App\Http\Requests\ReturnTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskProgressRequest;
use App\Models\Campus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskCodeService;
use App\Services\TaskReviewerResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Task::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'status' => ['nullable', Rule::in(Task::STATUSES)],
            'priority' => ['nullable', Rule::in(Task::PRIORITIES)],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
        ]);

        $visibleTasks = Task::query()->visibleTo($request->user());
        $tasks = (clone $visibleTasks)
            ->with(['project', 'assignees'])
            ->when($validated['search'] ?? null, function ($query, $search) {
                $search = trim($search);
                $query->where(function ($query) use ($search) {
                    $query->where('task_code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($validated['project_id'] ?? null, fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($validated['assignee_id'] ?? null, fn ($query, $userId) => $query->whereHas(
                'taskAssignees', fn ($query) => $query->where('user_id', $userId)->where('is_active', true)
            ))
            ->when($validated['campus_id'] ?? null, fn ($query, $campusId) => $query->whereHas('project', function ($query) use ($campusId) {
                $query->where('scope', 'university_wide')
                    ->orWhereHas('campuses', fn ($query) => $query->whereKey($campusId));
            }))
            ->orderByRaw('due_date is null, due_date asc')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'projects' => Project::query()->visibleTo($request->user())->orderBy('title')->get(),
            'assignees' => User::query()
                ->whereIn('id', (clone $visibleTasks)->select('task_assignees.user_id')
                    ->join('task_assignees', 'task_assignees.task_id', '=', 'tasks.id')
                    ->where('task_assignees.is_active', true))
                ->orderBy('name')->get(),
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(),
            'summary' => [
                'total' => (clone $visibleTasks)->count(),
                'not_started' => (clone $visibleTasks)->where('status', 'not_started')->count(),
                'in_progress' => (clone $visibleTasks)->where('status', 'in_progress')->count(),
                'pending_review' => (clone $visibleTasks)->where('status', 'pending_review')->count(),
                'completed' => (clone $visibleTasks)->where('status', 'completed')->count(),
                'overdue' => (clone $visibleTasks)->whereDate('due_date', '<', today())
                    ->whereNotIn('status', ['completed', 'cancelled'])->count(),
            ],
            'reviewQueue' => Task::query()->where('reviewer_id', $request->user()->id)
                ->where('status', 'pending_review')->with(['project', 'assignees'])
                ->orderBy('submitted_at')->get(),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Task::class);

        $projects = Project::query()
            ->visibleTo($request->user())
            ->where('is_active', true)
            ->with(['projectMembers' => fn ($query) => $query
                ->where('is_active', true)
                ->whereNull('left_at')
                ->whereHas('user', fn ($query) => $query->where('account_status', 'active')),
                'projectMembers.user.roles',
                'projectMembers.user.staffProfile.campus',
            ])
            ->orderBy('title')
            ->get();

        $preselectedProjectId = $projects->contains('id', $request->integer('project'))
            ? $request->integer('project')
            : null;

        return view('tasks.create', [
            'projects' => $projects,
            'preselectedProjectId' => $preselectedProjectId,
        ]);
    }

    public function store(StoreTaskRequest $request, TaskCodeService $codes)
    {
        $validated = $request->validated();

        $task = $codes->withNextCode(function (string $taskCode) use ($validated, $request) {
            return DB::transaction(function () use ($validated, $request, $taskCode) {
                $task = Task::create([
                    'task_code' => $taskCode,
                    'project_id' => $validated['project_id'],
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'created_by' => $request->user()->id,
                    'assigned_by' => $request->user()->id,
                    'start_date' => $validated['start_date'] ?? null,
                    'due_date' => $validated['due_date'] ?? null,
                    'priority' => $validated['priority'],
                    'status' => $validated['status'],
                    'progress_percentage' => $validated['progress_percentage'],
                    'estimated_hours' => $validated['estimated_hours'] ?? null,
                    'completed_at' => $validated['status'] === 'completed' ? now() : null,
                    'remarks' => $validated['remarks'] ?? null,
                    'is_active' => true,
                ]);

                $task->assignees()->attach(collect($validated['assignee_ids'])->mapWithKeys(
                    fn ($userId) => [(int) $userId => ['assigned_at' => now(), 'is_active' => true]]
                ));

                return $task;
            });
        });

        return redirect()->route('tasks.show', $task)->with('success', 'Task created successfully.');
    }

    public function show(Task $task)
    {
        Gate::authorize('view', $task);

        $task->load([
            'project', 'creator.roles', 'assignedBy.roles',
            'taskAssignees' => fn ($query) => $query->where('is_active', true),
            'taskAssignees.user.roles',
            'taskAssignees.user.staffProfile.campus',
            'subtasks.assignee', 'activities.user',
            'reviewer', 'submittedBy', 'reviewedBy', 'reviews.submitter', 'reviews.reviewer', 'reviews.reviewedBy',
        ]);

        return view('tasks.show', ['task' => $task]);
    }

    public function start(Request $request, Task $task)
    {
        Gate::authorize('execute', $task);
        if ($task->status !== 'not_started') {
            throw ValidationException::withMessages(['task' => 'Only a not-started task can be started.']);
        }
        DB::transaction(function () use ($task, $request) {
            $task->update(['status' => 'in_progress']);
            $task->activities()->create(['user_id' => $request->user()->id, 'activity_type' => 'task_started', 'old_status' => 'not_started', 'new_status' => 'in_progress']);
        });

        return back()->with('success', 'Task started.');
    }

    public function updateProgress(UpdateTaskProgressRequest $request, Task $task)
    {
        $data = $request->validated();
        if ($task->status !== 'in_progress') {
            throw ValidationException::withMessages(['task' => 'Progress can only be updated while the task is in progress.']);
        }
        DB::transaction(function () use ($task, $request, $data) {
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            if ($task->hasAutomaticProgress()) {
                throw ValidationException::withMessages(['progress_percentage' => 'Parent task progress is calculated automatically from active subtasks.']);
            }
            $oldProgress = $task->progress_percentage;
            $oldStatus = $task->status;
            $progress = (float) $data['progress_percentage'];
            $status = $oldStatus === 'not_started' && $progress > 0 ? 'in_progress' : $oldStatus;
            $task->update(['progress_percentage' => $progress, 'status' => $status]);
            $task->activities()->create(['user_id' => $request->user()->id, 'activity_type' => 'progress_updated', 'message' => $data['message'] ?? null, 'old_status' => $oldStatus, 'new_status' => $status, 'old_progress' => $oldProgress, 'new_progress' => $progress]);
        });

        return back()->with('success', 'Task progress updated.');
    }

    public function submitReview(Request $request, Task $task, TaskReviewerResolver $reviewers)
    {
        Gate::authorize('execute', $task);
        if ($task->status !== 'in_progress' || (float) $task->progress_percentage !== 100.0) {
            throw ValidationException::withMessages(['task' => 'The task must be in progress at 100% before submission.']);
        }
        if ($task->subtasks()->activeForProgress()->where(fn ($query) => $query->where('status', '!=', 'completed')->orWhere('progress_percentage', '<', 100))->exists()) {
            throw ValidationException::withMessages(['task' => 'All active subtasks must be completed before submission.']);
        }
        $reviewer = $reviewers->resolve($task);
        if (! $reviewer) {
            throw ValidationException::withMessages(['task' => 'No supervisor is configured to review this task. Please contact the appropriate administrator.']);
        }
        DB::transaction(function () use ($task, $request, $reviewer) {
            $submittedAt = now();
            $task->update(['status' => 'pending_review', 'reviewer_id' => $reviewer->id, 'submitted_by' => $request->user()->id, 'submitted_at' => $submittedAt, 'reviewed_by' => null, 'reviewed_at' => null, 'returned_at' => null]);
            $task->reviews()->create(['submitted_by' => $request->user()->id, 'reviewer_id' => $reviewer->id, 'submitted_at' => $submittedAt, 'status' => 'pending']);
            $task->activities()->create(['user_id' => $request->user()->id, 'activity_type' => 'submitted_for_review', 'message' => "Submitted to {$reviewer->name} for review.", 'old_status' => 'in_progress', 'new_status' => 'pending_review', 'old_progress' => $task->progress_percentage, 'new_progress' => $task->progress_percentage]);
        });

        return back()->with('success', "Task submitted to {$reviewer->name} for review.");
    }

    public function approve(ApproveTaskRequest $request, Task $task)
    {
        DB::transaction(function () use ($request, $task) {
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            Gate::authorize('review', $task);
            $review = $task->reviews()->where('status', 'pending')->latest('id')->lockForUpdate()->firstOrFail();
            $reviewedAt = now();
            $review->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => $reviewedAt, 'remark' => $request->validated('remark')]);
            $task->update(['status' => 'completed', 'progress_percentage' => 100, 'completed_at' => $reviewedAt, 'reviewed_by' => $request->user()->id, 'reviewed_at' => $reviewedAt, 'returned_at' => null]);
            $task->activities()->create(['user_id' => $request->user()->id, 'activity_type' => 'task_approved', 'message' => $request->validated('remark'), 'old_status' => 'pending_review', 'new_status' => 'completed', 'old_progress' => 100, 'new_progress' => 100]);
        });

        return back()->with('success', 'Task approved successfully.');
    }

    public function returnForCorrection(ReturnTaskRequest $request, Task $task)
    {
        DB::transaction(function () use ($request, $task) {
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            Gate::authorize('review', $task);
            $review = $task->reviews()->where('status', 'pending')->latest('id')->lockForUpdate()->firstOrFail();
            $reviewedAt = now();
            $reason = $request->validated('remark');
            $review->update(['status' => 'returned', 'reviewed_by' => $request->user()->id, 'reviewed_at' => $reviewedAt, 'remark' => $reason]);
            $task->update(['status' => 'in_progress', 'reviewed_by' => $request->user()->id, 'reviewed_at' => $reviewedAt, 'returned_at' => $reviewedAt, 'completed_at' => null]);
            $task->activities()->create(['user_id' => $request->user()->id, 'activity_type' => 'task_returned', 'message' => $reason, 'old_status' => 'pending_review', 'new_status' => 'in_progress', 'old_progress' => $task->progress_percentage, 'new_progress' => $task->progress_percentage]);
        });

        return back()->with('success', 'Task returned for correction.');
    }
}

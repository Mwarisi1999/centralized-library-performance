<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskTrackerController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('viewAny', Task::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(Task::STATUSES)],
            'priority' => ['nullable', Rule::in(Task::PRIORITIES)],
        ]);
        $user = $request->user();
        $assigned = Task::query()->whereHas('taskAssignees', fn ($query) => $query
            ->where('user_id', $user->id)->where('is_active', true));

        $tasks = (clone $assigned)
            ->with(['project', 'subtasks' => fn ($query) => $query->where('is_active', true)])
            ->withSum(['workEntries as logged_minutes' => fn ($query) => $query->where('user_id', $user->id)], 'duration_minutes')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(fn ($query) => $query->where('task_code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('project', fn ($query) => $query->where('title', 'like', "%{$search}%")));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->orderByRaw('due_date is null, due_date asc')->orderByDesc('created_at')
            ->paginate(15)->withQueryString();

        $total = (clone $assigned)->count();

        return view('task-tracker.index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'summary' => [
                'total' => $total,
                'completed' => (clone $assigned)->where('status', 'completed')->count(),
                'in_progress' => (clone $assigned)->where('status', 'in_progress')->count(),
                'overdue' => (clone $assigned)->whereDate('due_date', '<', today())->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'average_progress' => $total ? round((float) (clone $assigned)->avg('progress_percentage'), 1) : 0,
            ],
        ]);
    }
}

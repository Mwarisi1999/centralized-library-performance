<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubtaskRequest;
use App\Http\Requests\UpdateSubtaskProgressRequest;
use App\Models\Subtask;
use App\Models\Task;
use App\Services\SubtaskCodeService;
use App\Services\TaskProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SubtaskController extends Controller
{
    public function create(Task $task)
    {
        Gate::authorize('addSubtask', $task);
        $task->load(['project', 'taskAssignees' => fn ($q) => $q->where('is_active', true)->whereHas('user', fn ($q) => $q->where('account_status', 'active')), 'taskAssignees.user']);

        return view('subtasks.create', compact('task'));
    }

    public function store(StoreSubtaskRequest $request, Task $task, SubtaskCodeService $codes, TaskProgressService $progress)
    {
        $data = $request->validated();
        $subtask = $codes->withNextCode(fn ($code) => DB::transaction(function () use ($data, $request, $task, $code, $progress) {
            $oldProgress = $task->progress_percentage;
            $oldStatus = $task->status;
            $status = (float) $data['progress_percentage'] === 100.0 && $data['status'] !== 'cancelled'
                ? 'completed'
                : $data['status'];
            $subtask = $task->subtasks()->create([...$data, 'subtask_code' => $code, 'created_by' => $request->user()->id, 'status' => $status, 'completed_at' => $status === 'completed' ? now() : null, 'is_active' => true]);
            $progress->recalculate($task);
            $task->activities()->create(['user_id' => $request->user()->id, 'activity_type' => 'subtask_created', 'message' => "Created subtask {$code}: {$subtask->title}", 'old_status' => $oldStatus, 'new_status' => $task->status, 'old_progress' => $oldProgress, 'new_progress' => $task->progress_percentage]);

            return $subtask;
        }));

        return redirect()->route('subtasks.show', $subtask)->with('success', 'Subtask created successfully.');
    }

    public function show(Subtask $subtask)
    {
        Gate::authorize('view', $subtask);
        $subtask->load(['task.project', 'creator', 'assignee']);

        return view('subtasks.show', compact('subtask'));
    }

    public function start(Request $request, Subtask $subtask, TaskProgressService $progress)
    {
        Gate::authorize('execute', $subtask);
        if ($subtask->status !== 'not_started') {
            throw ValidationException::withMessages(['subtask' => 'Only a not-started subtask can be started.']);
        }
        $this->mutate($subtask, $request, $progress, 'subtask_updated', fn ($s) => $s->update(['status' => 'in_progress']), 'Started subtask');

        return back()->with('success', 'Subtask started.');
    }

    public function updateProgress(UpdateSubtaskProgressRequest $request, Subtask $subtask, TaskProgressService $progress)
    {
        $value = (float) $request->validated('progress_percentage');
        $type = $value === 100.0 ? 'subtask_completed' : 'subtask_updated';
        $this->mutate($subtask, $request, $progress, $type, function ($s) use ($value) {
            $status = $value === 100.0 ? 'completed' : ($s->status === 'completed' ? 'in_progress' : ($s->status === 'not_started' && $value > 0 ? 'in_progress' : $s->status));
            $s->update(['progress_percentage' => $value, 'status' => $status, 'completed_at' => $status === 'completed' ? now() : null]);
        }, $request->validated('message'));

        return back()->with('success', 'Subtask progress updated.');
    }

    public function complete(Request $request, Subtask $subtask, TaskProgressService $progress)
    {
        Gate::authorize('execute', $subtask);
        $this->mutate($subtask, $request, $progress, 'subtask_completed', fn ($s) => $s->update(['progress_percentage' => 100, 'status' => 'completed', 'completed_at' => now()]), "Completed subtask {$subtask->subtask_code}");

        return back()->with('success', 'Subtask completed.');
    }

    private function mutate(Subtask $subtask, Request $request, TaskProgressService $progress, string $type, callable $change, ?string $message): void
    {
        DB::transaction(function () use ($subtask, $request, $progress, $type, $change, $message) {
            $subtask = Subtask::query()->lockForUpdate()->findOrFail($subtask->id);
            $task = Task::query()->lockForUpdate()->findOrFail($subtask->task_id);
            $oldTaskProgress = $task->progress_percentage;
            $oldTaskStatus = $task->status;
            $oldSubStatus = $subtask->status;
            $change($subtask);
            $progress->recalculate($task);
            if ($type === 'subtask_updated' && $oldSubStatus === 'completed' && $subtask->status !== 'completed') {
                $type = 'subtask_reopened';
            }
            $task->activities()->create(['user_id' => $request->user()->id, 'activity_type' => $type, 'message' => $message ?: "Updated {$subtask->subtask_code} from ".Subtask::label($oldSubStatus).' to '.Subtask::label($subtask->status), 'old_status' => $oldTaskStatus, 'new_status' => $task->status, 'old_progress' => $oldTaskProgress, 'new_progress' => $task->progress_percentage]);
        });
    }
}

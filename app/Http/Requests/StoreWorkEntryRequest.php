<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\WorkEntry;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkEntryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $narrativeFields = [
            'work_description',
            'work_location',
            'output_deliverable',
            'challenge_encountered',
            'corrective_action',
            'support_required',
            'planned_next_activity',
            'remarks',
        ];

        foreach ($narrativeFields as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', WorkEntry::class);
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'subtask_id' => ['nullable', 'integer', 'exists:subtasks,id'],
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'work_description' => ['required', 'string', 'min:10', 'max:5000'],
            'output_deliverable' => ['nullable', 'string', 'max:5000'],
            'challenge_encountered' => ['nullable', 'string', 'max:5000'],
            'corrective_action' => ['nullable', 'string', 'max:5000'],
            'support_required' => ['nullable', 'string', 'max:5000'],
            'planned_next_activity' => ['nullable', 'string', 'max:5000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $project = Project::query()->find($this->integer('project_id'));
            $task = Task::query()->find($this->integer('task_id'));
            $subtask = $this->filled('subtask_id') ? Subtask::query()->find($this->integer('subtask_id')) : null;

            if ($project && ! $this->user()->can('view', $project)) {
                $validator->errors()->add('project_id', 'The selected project is not accessible to you.');
            }
            if ($project && ! $project->is_active) {
                $validator->errors()->add('project_id', 'The selected project is not active.');
            }
            if ($task && $task->project_id !== $this->integer('project_id')) {
                $validator->errors()->add('task_id', 'The selected task does not belong to the selected project.');
            }
            if ($task && (! $task->is_active || $task->status === 'cancelled')) {
                $validator->errors()->add('task_id', 'The selected task is not eligible for work entries.');
            }

            $assignedToTask = $task?->taskAssignees()->where('user_id', $this->user()->id)->where('is_active', true)->exists() ?? false;
            $assignedToSubtask = $subtask && $subtask->assigned_to === $this->user()->id && $subtask->is_active && $subtask->status !== 'cancelled';
            if ($task && ! $subtask && ! $assignedToTask) {
                $validator->errors()->add('task_id', 'You must be assigned to the selected task to record work directly against it.');
            }
            if ($subtask && ! $assignedToSubtask) {
                $validator->errors()->add('subtask_id', 'You must be assigned to the selected subtask to record work against it.');
            }
            if ($subtask && $subtask->task_id !== $this->integer('task_id')) {
                $validator->errors()->add('subtask_id', 'The selected subtask does not belong to the selected task.');
            }
            if ($subtask && (! $subtask->is_active || $subtask->status === 'cancelled')) {
                $validator->errors()->add('subtask_id', 'The selected subtask is not eligible for work entries.');
            }

            if (! $validator->errors()->hasAny(['work_date', 'start_time', 'end_time'])) {
                $overlap = WorkEntry::query()
                    ->where('user_id', $this->user()->id)
                    ->whereDate('work_date', $this->input('work_date'))
                    ->overlapping($this->input('start_time'), $this->input('end_time'))
                    ->when($this->route('workEntry'), fn ($query, WorkEntry $workEntry) => $query->where('id', '!=', $workEntry->id))
                    ->orderBy('start_time')
                    ->first();

                if ($overlap) {
                    $start = Carbon::parse($overlap->start_time)->format('H:i');
                    $end = Carbon::parse($overlap->end_time)->format('H:i');

                    $validator->errors()->add(
                        'start_time',
                        "This work session overlaps with {$overlap->entry_code} ({$start}–{$end})."
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'work_date.before_or_equal' => 'The work date cannot be in the future.',
            'end_time.after' => 'End time must be later than start time.',
            'work_description.required' => 'Describe the work completed during this session.',
            'work_description.min' => 'The work description must contain at least 10 characters.',
        ];
    }
}

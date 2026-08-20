<?php

namespace App\Http\Requests;

use App\Models\Subtask;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addSubtask', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'date'], 'due_date' => ['nullable', 'date'], 'priority' => ['required', Rule::in(Subtask::PRIORITIES)],
            'status' => ['required', Rule::in(Subtask::STATUSES)], 'progress_percentage' => ['required', 'numeric', 'between:0,100'], 'estimated_hours' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $task = $this->route('task');
            if ($this->filled('assigned_to') && ! $task->taskAssignees()->where('user_id', $this->integer('assigned_to'))->where('is_active', true)->whereHas('user', fn ($q) => $q->where('account_status', 'active'))->exists()) {
                $validator->errors()->add('assigned_to', 'The assignee must be an active assignee of the parent task.');
            }
            if (! $validator->errors()->hasAny(['start_date', 'due_date']) && $this->filled('start_date') && $this->filled('due_date') && Carbon::parse($this->input('due_date'))->lt(Carbon::parse($this->input('start_date')))) {
                $validator->errors()->add('due_date', 'The due date must be on or after the start date.');
            }
            if ($this->input('status') === 'completed' && (float) $this->input('progress_percentage') !== 100.0) {
                $validator->errors()->add('progress_percentage', 'Completed subtasks must have 100% progress.');
            }
        });
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class TimesheetFilterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $defaults = [];
        if (! $this->has('date_from')) {
            $defaults['date_from'] = now()->startOfMonth()->format('Y-m-d');
        }
        if (! $this->has('date_to')) {
            $defaults['date_to'] = today()->format('Y-m-d');
        }
        $this->merge($defaults);
    }

    public function authorize(): bool
    {
        return $this->user()?->account_status === 'active';
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'subtask_id' => ['nullable', 'integer', 'exists:subtasks,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('project_id') && $this->filled('task_id')) {
                $task = Task::query()->find($this->integer('task_id'));
                if ($task && $task->project_id !== $this->integer('project_id')) {
                    $validator->errors()->add('task_id', 'The selected task does not belong to the selected project.');
                }
            }
            if ($this->filled('task_id') && $this->filled('subtask_id')) {
                $subtask = Subtask::query()->find($this->integer('subtask_id'));
                if ($subtask && $subtask->task_id !== $this->integer('task_id')) {
                    $validator->errors()->add('subtask_id', 'The selected subtask does not belong to the selected task.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'date_from.date' => 'Date From must be a valid date.',
            'date_to.date' => 'Date To must be a valid date.',
            'date_to.after_or_equal' => 'Date To must be on or after Date From.',
        ];
    }
}

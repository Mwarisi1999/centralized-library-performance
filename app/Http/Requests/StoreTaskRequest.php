<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['assignee_ids' => $this->input('assignee_ids', [])]);
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', Task::class);
    }

    public function rules(): array
    {
        return [
            'project_id' => [
                'required', 'integer',
                Rule::exists('projects', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)->whereNull('deleted_at')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_ids' => ['required', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'status' => ['required', Rule::in(Task::STATUSES)],
            'progress_percentage' => ['required', 'integer', 'between:0,100'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $project = $this->integer('project_id')
                ? Project::query()->find($this->integer('project_id'))
                : null;

            if ($project && ! $this->user()->can('createForProject', [Task::class, $project])) {
                $validator->errors()->add('project_id', 'You are not authorized to create tasks for this project.');
            }

            if ($project && is_array($this->input('assignee_ids'))) {
                $eligibleIds = DB::table('project_members')
                    ->join('users', 'users.id', '=', 'project_members.user_id')
                    ->where('project_members.project_id', $project->id)
                    ->where('project_members.is_active', true)
                    ->whereNull('project_members.left_at')
                    ->where('users.account_status', 'active')
                    ->whereIn('users.id', $this->input('assignee_ids'))
                    ->pluck('users.id')
                    ->map(fn ($id) => (int) $id);

                foreach ($this->input('assignee_ids') as $index => $userId) {
                    if (! $eligibleIds->contains((int) $userId)) {
                        $validator->errors()->add("assignee_ids.{$index}", 'Each assignee must be an active member of the selected project.');
                    }
                }
            }

            if (! $validator->errors()->hasAny(['start_date', 'due_date'])
                && $this->filled('start_date') && $this->filled('due_date')
                && Carbon::parse($this->input('due_date'))->lt(Carbon::parse($this->input('start_date')))) {
                $validator->errors()->add('due_date', 'The due date must be on or after the start date.');
            }

            if ($this->input('status') === 'completed' && $this->integer('progress_percentage') !== 100) {
                $validator->errors()->add('progress_percentage', 'Completed tasks must have 100% progress.');
            }
        });
    }
}

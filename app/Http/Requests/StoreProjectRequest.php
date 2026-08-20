<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'campus_ids' => $this->input('campus_ids', []),
            'member_ids' => $this->input('member_ids', []),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'project_category_id' => [
                'nullable',
                Rule::exists('project_categories', 'id')->where('is_active', true),
            ],
            'description' => ['nullable', 'string'],
            'scope' => ['required', Rule::in(Project::SCOPES)],
            'campus_ids' => [
                Rule::excludeIf(fn () => $this->input('scope') !== 'selected_campuses'),
                Rule::requiredIf(fn () => $this->input('scope') === 'selected_campuses'),
                'array',
                'min:1',
            ],
            'campus_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('campuses', 'id')->where('is_active', true),
            ],
            'owner_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('account_status', 'active'),
            ],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('account_status', 'active'),
            ],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'priority_level' => ['required', Rule::in(Project::PRIORITIES)],
            'progress_method' => ['required', Rule::in(Project::PROGRESS_METHODS)],
            'objectives' => ['nullable', 'string'],
            'expected_deliverables' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'campus_ids.required' => 'Select at least one campus for the selected-campus scope.',
            'campus_ids.min' => 'Select at least one campus for the selected-campus scope.',
            'due_date.after_or_equal' => 'The due date must be on or after the start date.',
        ];
    }
}

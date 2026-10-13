<?php

namespace App\Http\Requests;

use App\Models\Position;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'campus_ids' => $this->input('campus_ids', []),
            'member_ids' => $this->input('member_ids', []),
            'job_description_duties' => $this->input('job_description_duties', []),
            'job_description_other' => $this->boolean('job_description_other'),
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
            'position_id' => [
                'required',
                'integer',
                Rule::exists('positions', 'id')->where('is_active', true)->whereNull('deleted_at'),
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
            'job_description_duties' => ['array'],
            'job_description_duties.*' => ['string', 'distinct', 'max:5000'],
            'job_description_other' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $selectedDuties = collect($this->input('job_description_duties', []))
                    ->filter(fn ($duty) => is_string($duty) && trim($duty) !== '')
                    ->values();

                if ($selectedDuties->isEmpty() && ! $this->boolean('job_description_other')) {
                    $validator->errors()->add(
                        'job_description_duties',
                        'Select at least one duty from the job description or select Other.',
                    );
                }

                $project = $this->route('project');
                $position = Position::query()->with('jobDetail')->find($this->integer('position_id'));
                $currentDuties = collect($position?->jobDetail?->duties ?? []);
                $existingDuties = $project instanceof Project
                    && (int) $project->position_id === $this->integer('position_id')
                    ? $project->jobDescriptionItems()->where('is_other', false)->pluck('duty_text')
                    : collect();
                $allowedDuties = $currentDuties->merge($existingDuties)->unique()->values();

                if ($selectedDuties->diff($allowedDuties)->isNotEmpty()) {
                    $validator->errors()->add(
                        'job_description_duties',
                        "One or more selected duties do not belong to the selected position's job description.",
                    );
                }
            },
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

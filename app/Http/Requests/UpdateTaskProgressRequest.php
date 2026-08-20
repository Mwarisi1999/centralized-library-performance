<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('execute', $this->route('task'));
    }

    public function rules(): array
    {
        return ['progress_percentage' => ['required', 'numeric', 'between:0,100'], 'message' => ['nullable', 'string', 'max:2000']];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->route('task')->hasAutomaticProgress()) {
                $validator->errors()->add('progress_percentage', 'Parent task progress is calculated automatically from active subtasks.');
            }
        });
    }
}

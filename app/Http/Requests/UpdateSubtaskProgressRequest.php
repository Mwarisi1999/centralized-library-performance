<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubtaskProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('execute', $this->route('subtask'));
    }

    public function rules(): array
    {
        return ['progress_percentage' => ['required', 'numeric', 'between:0,100'], 'message' => ['nullable', 'string', 'max:2000']];
    }
}

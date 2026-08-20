<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('task'));
    }

    public function rules(): array
    {
        return ['remark' => ['nullable', 'string', 'max:3000']];
    }
}

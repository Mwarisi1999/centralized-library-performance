<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('task'));
    }

    public function rules(): array
    {
        return ['remark' => ['required', 'string', 'max:3000']];
    }
}

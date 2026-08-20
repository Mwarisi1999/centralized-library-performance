<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnMonthlyReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('correction_reason'))) {
            $this->merge(['correction_reason' => trim($this->input('correction_reason'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('monthlyReport'));
    }

    public function rules(): array
    {
        return ['correction_reason' => ['required', 'string', 'max:3000']];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MonthlyReportPeriodRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'month' => $this->has('month') ? $this->input('month') : today()->month,
            'year' => $this->has('year') ? $this->input('year') : today()->year,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->account_status === 'active';
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,'.(today()->year + 1)],
        ];
    }

    public function messages(): array
    {
        return [
            'month.between' => 'The reporting month must be between 1 and 12.',
            'year.between' => 'The reporting year must be between 2000 and '.(today()->year + 1).'.',
        ];
    }
}

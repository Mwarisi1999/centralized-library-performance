<?php

namespace App\Http\Requests;

use App\Models\CampusMonthlyReport;
use Illuminate\Foundation\Http\FormRequest;

class CampusMonthlyReportRequest extends FormRequest
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
        return $this->user()?->can($this->isMethod('post') ? 'finalize' : 'viewAny', CampusMonthlyReport::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,'.(today()->year + 1)],
        ];
    }

    public function period(): array
    {
        return [(int) $this->validated('month'), (int) $this->validated('year')];
    }
}

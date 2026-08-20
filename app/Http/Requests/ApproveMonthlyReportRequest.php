<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveMonthlyReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('approval_remark'))) {
            $this->merge(['approval_remark' => trim($this->input('approval_remark'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('monthlyReport'));
    }

    public function rules(): array
    {
        return ['approval_remark' => ['nullable', 'string', 'max:3000']];
    }
}

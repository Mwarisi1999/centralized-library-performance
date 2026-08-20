<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UniversityDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->account_status === 'active'
            && $this->user()->hasRole('University Librarian')
            && $this->user()->can('view university dashboard');
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,'.(now()->year + 1)],
        ];
    }

    public function period(): array
    {
        return [
            (int) ($this->validated('month') ?? now()->month),
            (int) ($this->validated('year') ?? now()->year),
        ];
    }
}

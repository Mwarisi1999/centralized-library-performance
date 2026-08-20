<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CampusDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $profile = $user?->staffProfile()->with('campus')->first();

        return $user?->account_status === 'active'
            && $user->hasRole('Campus Librarian')
            && $user->can('view campus dashboard')
            && $profile?->status === 'active'
            && (bool) $profile->campus?->is_active;
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

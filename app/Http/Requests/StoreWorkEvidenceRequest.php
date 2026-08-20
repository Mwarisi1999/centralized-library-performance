<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreWorkEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workEntry = $this->route('workEntry');

        return $workEntry
            && $this->user()->can('addEvidence', $workEntry);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['file', 'link'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'evidence_file' => [
                Rule::requiredIf($this->input('type') === 'file'),
                'nullable',
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'webp'])->max('10mb'),
            ],
            'url' => [Rule::requiredIf($this->input('type') === 'link'), 'nullable', 'url:http,https', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'evidence_file.required' => 'Select an evidence file to upload.',
            'url.required' => 'Enter the evidence URL.',
            'url.url' => 'The evidence URL must be a valid HTTP or HTTPS web address.',
        ];
    }
}

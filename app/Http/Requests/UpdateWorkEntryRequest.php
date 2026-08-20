<?php

namespace App\Http\Requests;

use App\Models\WorkEntry;

class UpdateWorkEntryRequest extends StoreWorkEntryRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('workEntry'));
    }

    public function withValidator($validator): void
    {
        parent::withValidator($validator);

        $validator->after(function ($validator) {
            $workEntry = $this->route('workEntry');
            if (! $workEntry instanceof WorkEntry || $validator->errors()->has('work_date')) {
                return;
            }

            $newDate = date_create_immutable($this->input('work_date'));
            if (! $newDate
                || (int) $newDate->format('n') !== $workEntry->work_date->month
                || (int) $newDate->format('Y') !== $workEntry->work_date->year) {
                $validator->errors()->add('work_date', 'The work date must remain within the returned monthly report period.');
            }
        });
    }
}

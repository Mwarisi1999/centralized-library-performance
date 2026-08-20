<?php

namespace App\Policies;

use App\Models\MonthlyReport;
use App\Models\User;
use App\Models\WorkEntry;

class WorkEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->account_status === 'active';
    }

    public function create(User $user): bool
    {
        return $user->account_status === 'active';
    }

    public function view(User $user, WorkEntry $workEntry): bool
    {
        if ($workEntry->user_id === $user->id || $user->can('view all timesheets')) {
            return true;
        }

        return $user->can('view supervised timesheets')
            && $workEntry->user?->staffProfile?->supervisor_id === $user->id;
    }

    public function addEvidence(User $user, WorkEntry $workEntry): bool
    {
        return $workEntry->user_id === $user->id
            && $user->can('upload evidence')
            && $workEntry->isEvidenceEditable();
    }

    public function update(User $user, WorkEntry $workEntry): bool
    {
        return $user->account_status === 'active'
            && $workEntry->user_id === $user->id
            && $user->can('edit own timesheet entries')
            && MonthlyReport::query()
                ->where('user_id', $user->id)
                ->where('reporting_month', $workEntry->work_date->month)
                ->where('reporting_year', $workEntry->work_date->year)
                ->where('status', MonthlyReport::STATUS_RETURNED_FOR_CORRECTION)
                ->exists();
    }
}

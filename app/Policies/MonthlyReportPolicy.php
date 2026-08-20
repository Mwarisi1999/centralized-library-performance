<?php

namespace App\Policies;

use App\Models\MonthlyReport;
use App\Models\User;

class MonthlyReportPolicy
{
    public function view(User $user, MonthlyReport $report): bool
    {
        return $user->account_status === 'active'
            && (($report->user_id === $user->id && $user->can('view own reports'))
                || ($report->reviewer_id === $user->id && $user->can('view supervised reports')));
    }

    public function submit(User $user, MonthlyReport $report): bool
    {
        return $user->account_status === 'active'
            && $user->can('submit reports')
            && $report->user_id === $user->id
            && in_array($report->status, [MonthlyReport::STATUS_DRAFT, MonthlyReport::STATUS_RETURNED_FOR_CORRECTION], true);
    }

    public function review(User $user, MonthlyReport $report): bool
    {
        return $user->account_status === 'active'
            && $user->can('review reports')
            && $report->reviewer_id === $user->id
            && $report->status === MonthlyReport::STATUS_PENDING_REVIEW;
    }
}

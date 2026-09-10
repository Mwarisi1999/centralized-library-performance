<?php

namespace App\Policies;

use App\Models\MonthlyReport;
use App\Models\User;

class MonthlyReportPolicy
{
    public function view(User $user, MonthlyReport $report): bool
    {
        if ($user->account_status !== 'active') {
            return false;
        }

        if ($user->hasRole('University Librarian') && $user->can('view all reports')) {
            return true;
        }

        $sameCampusOversight = $user->hasRole('Campus Librarian')
            && $user->can('view supervised reports')
            && $user->staffProfile?->status === 'active'
            && $user->staffProfile?->campus?->is_active
            && $report->user?->staffProfile?->status === 'active'
            && $user->staffProfile->campus_id === $report->user?->staffProfile?->campus_id;

        return ($report->user_id === $user->id && $user->can('view own reports'))
            || ($report->reviewer_id === $user->id && $user->can('view supervised reports'))
            || $sameCampusOversight;
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
        $ordinaryReviewer = $report->reviewer_id === $user->id;
        $universityOverride = $user->hasRole('University Librarian') && $user->can('view all reports');

        return $user->account_status === 'active'
            && $user->can('review reports')
            && $user->can('approve reports')
            && $user->can('return reports')
            && ($ordinaryReviewer || $universityOverride)
            && $report->status === MonthlyReport::STATUS_PENDING_REVIEW;
    }
}

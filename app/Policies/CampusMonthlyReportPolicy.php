<?php

namespace App\Policies;

use App\Models\CampusMonthlyReport;
use App\Models\User;

class CampusMonthlyReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorizedCampusId($user) !== null && $user->can('view campus reports');
    }

    public function view(User $user, CampusMonthlyReport $report): bool
    {
        if ($user->account_status === 'active' && $user->hasRole('University Librarian') && $user->can('view university dashboard')) {
            return true;
        }

        return $this->viewAny($user) && $report->campus_id === $this->authorizedCampusId($user);
    }

    public function finalize(User $user): bool
    {
        return $this->authorizedCampusId($user) !== null && $user->can('finalize campus reports');
    }

    private function authorizedCampusId(User $user): ?int
    {
        if (! $user->hasRole('Campus Librarian') || $user->account_status !== 'active') {
            return null;
        }

        $profile = $user->staffProfile()->with('campus:id,is_active')->first();

        return $profile?->status === 'active' && $profile->campus?->is_active ? $profile->campus_id : null;
    }
}

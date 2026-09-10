<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonthlyReportPeriodRequest;
use App\Models\User;
use App\Services\IndividualPerformanceService;
use Illuminate\View\View;

class StaffPerformanceController extends Controller
{
    public function show(MonthlyReportPeriodRequest $request, User $staff, IndividualPerformanceService $performance): View
    {
        $viewer = $request->user();
        $isUniversity = $viewer->hasRole('University Librarian') && $viewer->can('view university dashboard');
        $isCampus = $viewer->hasRole('Campus Librarian') && $viewer->can('view campus dashboard')
            && $viewer->staffProfile?->status === 'active'
            && $viewer->staffProfile?->campus?->is_active
            && $viewer->staffProfile->campus_id === $staff->staffProfile?->campus_id;

        abort_unless(($isUniversity || $isCampus)
            && $staff->account_status === 'active'
            && $staff->staffProfile?->status === 'active', 403);

        $validated = $request->validated();

        return view('performance.show', $performance->for($staff, (int) $validated['month'], (int) $validated['year']));
    }
}

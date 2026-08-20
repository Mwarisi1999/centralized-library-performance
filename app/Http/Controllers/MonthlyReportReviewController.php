<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveMonthlyReportRequest;
use App\Http\Requests\ReturnMonthlyReportRequest;
use App\Models\MonthlyReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MonthlyReportReviewController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('review reports'), 403);

        return view('monthly-reports.reviews.index', [
            'reports' => MonthlyReport::query()
                ->where('reviewer_id', $request->user()->id)
                ->where('status', MonthlyReport::STATUS_PENDING_REVIEW)
                ->with(['user.staffProfile.position', 'user.staffProfile.campus', 'user.staffProfile.library'])
                ->latest('submitted_at')
                ->paginate(15),
        ]);
    }

    public function show(MonthlyReport $monthlyReport): View
    {
        Gate::authorize('view', $monthlyReport);
        abort_unless($monthlyReport->reviewer_id === auth()->id(), 403);
        $monthlyReport->load(['user', 'reviewer', 'submitter', 'activities.user']);

        return view('monthly-reports.reviews.show', [
            'report' => $monthlyReport,
            'snapshot' => $monthlyReport->submitted_snapshot,
        ]);
    }

    public function approve(ApproveMonthlyReportRequest $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        DB::transaction(function () use ($request, $monthlyReport) {
            $report = MonthlyReport::query()->lockForUpdate()->findOrFail($monthlyReport->id);
            Gate::authorize('review', $report);
            $reviewedAt = now();
            $report->update([
                'status' => MonthlyReport::STATUS_APPROVED,
                'reviewed_at' => $reviewedAt,
                'approved_at' => $reviewedAt,
                'returned_at' => null,
                'approval_remark' => $request->validated('approval_remark'),
                'correction_reason' => null,
            ]);
            $report->activities()->create([
                'user_id' => $request->user()->id,
                'event' => 'report_approved',
                'description' => "Approved {$report->report_code}.",
                'metadata' => filled($request->validated('approval_remark'))
                    ? ['remark' => $request->validated('approval_remark')]
                    : null,
            ]);
        });

        return redirect()->route('monthly-reports.reviews.index')->with('success', 'Monthly report approved successfully.');
    }

    public function returnForCorrection(ReturnMonthlyReportRequest $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        DB::transaction(function () use ($request, $monthlyReport) {
            $report = MonthlyReport::query()->lockForUpdate()->findOrFail($monthlyReport->id);
            Gate::authorize('review', $report);
            $reviewedAt = now();
            $reason = $request->validated('correction_reason');
            $report->update([
                'status' => MonthlyReport::STATUS_RETURNED_FOR_CORRECTION,
                'reviewed_at' => $reviewedAt,
                'approved_at' => null,
                'returned_at' => $reviewedAt,
                'approval_remark' => null,
                'correction_reason' => $reason,
            ]);
            $report->activities()->create([
                'user_id' => $request->user()->id,
                'event' => 'report_returned',
                'description' => "Returned {$report->report_code} for correction.",
                'metadata' => ['reason' => $reason],
            ]);
        });

        return redirect()->route('monthly-reports.reviews.index')->with('success', 'Monthly report returned for correction.');
    }
}

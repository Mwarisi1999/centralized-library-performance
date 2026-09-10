<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveMonthlyReportRequest;
use App\Http\Requests\ReturnMonthlyReportRequest;
use App\Models\MonthlyReport;
use App\Services\WorkflowNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MonthlyReportReviewController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('review reports') || $request->user()->can('view all reports'), 403);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(MonthlyReport::STATUSES)],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $isUniversityLibrarian = $request->user()->hasRole('University Librarian')
            && $request->user()->can('view all reports');

        return view('monthly-reports.reviews.index', [
            'reports' => MonthlyReport::query()
                ->when(! $isUniversityLibrarian, fn ($query) => $query
                    ->where('reviewer_id', $request->user()->id)
                    ->where('status', MonthlyReport::STATUS_PENDING_REVIEW))
                ->when($isUniversityLibrarian && filled($validated['status'] ?? null), fn ($query) => $query->where('status', $validated['status']))
                ->when($isUniversityLibrarian && blank($validated['status'] ?? null), fn ($query) => $query->where('status', MonthlyReport::STATUS_PENDING_REVIEW))
                ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested
                    ->where('report_code', 'like', '%'.trim($search).'%')
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.trim($search).'%'))))
                ->with(['user.staffProfile.position', 'user.staffProfile.campus', 'user.staffProfile.library'])
                ->latest('submitted_at')
                ->paginate(15)->withQueryString(),
            'isUniversityLibrarian' => $isUniversityLibrarian,
        ]);
    }

    public function show(MonthlyReport $monthlyReport): View
    {
        Gate::authorize('view', $monthlyReport);
        $monthlyReport->load(['user.staffProfile.campus', 'reviewer', 'submitter', 'activities.user']);

        return view('monthly-reports.reviews.show', [
            'report' => $monthlyReport,
            'snapshot' => $monthlyReport->submitted_snapshot,
            'canReview' => Gate::allows('review', $monthlyReport),
            'isOverride' => auth()->user()->hasRole('University Librarian') && $monthlyReport->reviewer_id !== auth()->id(),
        ]);
    }

    public function approve(ApproveMonthlyReportRequest $request, MonthlyReport $monthlyReport, WorkflowNotificationService $notifications): RedirectResponse
    {
        DB::transaction(function () use ($request, $monthlyReport, $notifications) {
            $report = MonthlyReport::query()->lockForUpdate()->findOrFail($monthlyReport->id);
            Gate::authorize('review', $report);
            $override = $request->user()->hasRole('University Librarian') && $report->reviewer_id !== $request->user()->id;
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
                'event' => $override ? 'report_override_approved' : 'report_approved',
                'description' => ($override ? 'University Librarian override: ' : '')."Approved {$report->report_code}.",
                'metadata' => array_filter([
                    'remark' => $request->validated('approval_remark'),
                    'university_librarian_override' => $override,
                    'original_reviewer_id' => $report->reviewer_id,
                    'performed_by_id' => $request->user()->id,
                ], fn ($value) => $value !== null),
            ]);
            $notifications->send($report->user, 'monthly_report_approved', 'Monthly report approved', "{$report->report_code} was approved by {$request->user()->name}.", route('my-work.monthly-report', ['month' => $report->reporting_month, 'year' => $report->reporting_year]), "report-approved:{$report->id}:{$report->reviewed_at?->timestamp}", 'success');
            if ($override && $report->reviewer && $report->reviewer_id !== $report->user_id) {
                $notifications->send($report->reviewer, 'monthly_report_override', 'Report reviewed by University Librarian', "{$report->report_code}, originally assigned to you, was approved by {$request->user()->name}.", route('monthly-reports.reviews.show', $report), "report-override-approved:{$report->id}");
            }
        });

        return redirect()->route('monthly-reports.reviews.index')->with('success', 'Monthly report approved successfully.');
    }

    public function returnForCorrection(ReturnMonthlyReportRequest $request, MonthlyReport $monthlyReport, WorkflowNotificationService $notifications): RedirectResponse
    {
        DB::transaction(function () use ($request, $monthlyReport, $notifications) {
            $report = MonthlyReport::query()->lockForUpdate()->findOrFail($monthlyReport->id);
            Gate::authorize('review', $report);
            $override = $request->user()->hasRole('University Librarian') && $report->reviewer_id !== $request->user()->id;
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
                'event' => $override ? 'report_override_returned' : 'report_returned',
                'description' => ($override ? 'University Librarian override: ' : '')."Returned {$report->report_code} for correction.",
                'metadata' => ['reason' => $reason, 'university_librarian_override' => $override, 'original_reviewer_id' => $report->reviewer_id, 'performed_by_id' => $request->user()->id],
            ]);
            $notifications->send($report->user, 'monthly_report_returned', 'Monthly report returned', "{$report->report_code} was returned for correction: {$reason}", route('my-work.monthly-report', ['month' => $report->reporting_month, 'year' => $report->reporting_year]), "report-returned:{$report->id}:{$report->reviewed_at?->timestamp}", 'warning');
            if ($override && $report->reviewer && $report->reviewer_id !== $report->user_id) {
                $notifications->send($report->reviewer, 'monthly_report_override', 'Report reviewed by University Librarian', "{$report->report_code}, originally assigned to you, was returned by {$request->user()->name}.", route('monthly-reports.reviews.show', $report), "report-override-returned:{$report->id}");
            }
        });

        return redirect()->route('monthly-reports.reviews.index')->with('success', 'Monthly report returned for correction.');
    }
}

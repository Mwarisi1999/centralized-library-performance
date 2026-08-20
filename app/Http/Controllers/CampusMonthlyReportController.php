<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampusMonthlyReportRequest;
use App\Models\CampusMonthlyReport;
use App\Services\CampusMonthlyReportService;
use App\Services\CampusMonthlyReportWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CampusMonthlyReportController extends Controller
{
    public function index(CampusMonthlyReportRequest $request, CampusMonthlyReportService $reports): View
    {
        $campus = $reports->campusFor($request->user());
        $history = CampusMonthlyReport::query()->where('campus_id', $campus->id)
            ->with('finalizer:id,name')->latest('reporting_year')->latest('reporting_month')->paginate(12);

        return view('campus-reports.index', compact('campus', 'history'));
    }

    public function create(CampusMonthlyReportRequest $request, CampusMonthlyReportService $reports): View
    {
        [$month, $year] = $request->period();

        return view('campus-reports.show', $reports->reportFor($request->user(), $month, $year));
    }

    public function show(CampusMonthlyReport $campusReport): View
    {
        Gate::authorize('view', $campusReport);
        $campusReport->loadMissing(['campus', 'finalizer:id,name']);

        return view('campus-reports.show', ['report' => $campusReport, 'data' => $campusReport->snapshot, 'isFrozen' => true]);
    }

    public function finalize(CampusMonthlyReportRequest $request, CampusMonthlyReportWorkflowService $workflow): RedirectResponse
    {
        [$month, $year] = $request->period();
        $report = $workflow->finalize($request->user(), $month, $year);

        return redirect()->route('campus-reports.show', $report)->with('success', 'Campus monthly report finalized.');
    }
}

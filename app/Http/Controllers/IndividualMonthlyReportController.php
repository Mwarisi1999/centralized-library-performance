<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonthlyReportPeriodRequest;
use App\Services\IndividualMonthlyReportService;
use App\Services\MonthlyReportWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class IndividualMonthlyReportController extends Controller
{
    public function __invoke(MonthlyReportPeriodRequest $request, IndividualMonthlyReportService $reports): View
    {
        $period = $request->validated();

        return view('my-work.monthly-report', $reports->foundationFor(
            $request->user(),
            (int) $period['month'],
            (int) $period['year'],
        ));
    }

    public function submit(MonthlyReportPeriodRequest $request, MonthlyReportWorkflowService $workflow): RedirectResponse
    {
        $period = $request->validated();
        $report = $workflow->submit($request->user(), (int) $period['month'], (int) $period['year']);

        return redirect()
            ->route('my-work.monthly-report', ['month' => $report->reporting_month, 'year' => $report->reporting_year])
            ->with('success', 'Monthly report submitted for review.');
    }
}

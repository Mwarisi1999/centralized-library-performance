<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonthlyReportPeriodRequest;
use App\Services\TimesheetReportService;
use Illuminate\Contracts\View\View;

class PrintableTimesheetController extends Controller
{
    public function __invoke(MonthlyReportPeriodRequest $request, TimesheetReportService $timesheets): View
    {
        $period = $request->validated();

        return view('printable-timesheet.index', $timesheets->monthlyFor(
            $request->user(),
            (int) $period['month'],
            (int) $period['year'],
        ));
    }
}

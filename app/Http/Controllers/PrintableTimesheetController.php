<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonthlyReportPeriodRequest;
use App\Models\WorkEntry;
use App\Services\TimesheetReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class PrintableTimesheetController extends Controller
{
    public function __invoke(MonthlyReportPeriodRequest $request, TimesheetReportService $timesheets): View
    {
        Gate::authorize('viewAny', WorkEntry::class);

        $period = $request->validated();

        return view('printable-timesheet.index', $timesheets->monthlyFor(
            $request->user(),
            (int) $period['month'],
            (int) $period['year'],
        ));
    }
}

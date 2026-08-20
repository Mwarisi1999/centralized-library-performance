<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonthlyReportPeriodRequest;
use App\Services\ReportFileService;
use App\Services\TimesheetReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimesheetReportController extends Controller
{
    public function print(MonthlyReportPeriodRequest $request, TimesheetReportService $timesheets): View
    {
        [$month, $year] = $this->period($request);

        return view('reports.timesheet', $timesheets->monthlyFor($request->user(), $month, $year) + ['isPdf' => false]);
    }

    public function pdf(MonthlyReportPeriodRequest $request, TimesheetReportService $timesheets, ReportFileService $files): Response
    {
        [$month, $year] = $this->period($request);
        $data = $timesheets->monthlyFor($request->user(), $month, $year);

        return $files->pdf('reports.timesheet', $data, "timesheet_{$request->user()->name}_{$year}_".str_pad((string) $month, 2, '0', STR_PAD_LEFT).'.pdf', 'landscape');
    }

    public function csv(MonthlyReportPeriodRequest $request, TimesheetReportService $timesheets, ReportFileService $files): StreamedResponse
    {
        [$month, $year] = $this->period($request);
        $data = $timesheets->monthlyFor($request->user(), $month, $year);
        $rows = $data['entries']->map(fn ($entry) => [
            $entry->entry_code, $entry->work_date->format('Y-m-d'), $entry->project?->project_code,
            $entry->project?->title, $entry->task?->task_code, $entry->task?->title,
            $entry->subtask?->subtask_code, $entry->subtask?->title, $entry->start_time,
            $entry->end_time, round($entry->duration_minutes / 60, 2), $entry->work_description,
            $entry->output_deliverable, $entry->challenge_encountered, $entry->corrective_action,
            $entry->support_required, $entry->planned_next_activity, $entry->remarks, $entry->work_location,
        ]);

        return $files->csv([
            'Work-entry Code', 'Work Date', 'Project Code', 'Project', 'Task Code', 'Task',
            'Subtask Code', 'Subtask', 'Start Time', 'End Time', 'Calculated Hours',
            'Work Description', 'Output / Deliverable', 'Challenge Encountered',
            'Corrective Action Taken', 'Support Required', 'Follow-up / Planned Next Activity', 'Remarks', 'Work Location',
        ], $rows, "timesheet_{$request->user()->name}_{$year}_".str_pad((string) $month, 2, '0', STR_PAD_LEFT).'.csv');
    }

    private function period(MonthlyReportPeriodRequest $request): array
    {
        $period = $request->validated();

        return [(int) $period['month'], (int) $period['year']];
    }
}

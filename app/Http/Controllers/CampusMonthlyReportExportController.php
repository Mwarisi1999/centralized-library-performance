<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampusMonthlyReportRequest;
use App\Models\CampusMonthlyReport;
use App\Services\CampusMonthlyReportService;
use App\Services\ReportFileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampusMonthlyReportExportController extends Controller
{
    public function printLive(CampusMonthlyReportRequest $request, CampusMonthlyReportService $reports): View
    {
        [$month, $year] = $request->period();

        return view('reports.campus-monthly', $reports->reportFor($request->user(), $month, $year) + ['isPdf' => false]);
    }

    public function pdfLive(CampusMonthlyReportRequest $request, CampusMonthlyReportService $reports, ReportFileService $files): Response
    {
        [$month, $year] = $request->period();
        $payload = $reports->reportFor($request->user(), $month, $year);
        $code = $payload['report']?->report_code ?? "draft-{$year}-{$month}";

        return $files->pdf('reports.campus-monthly', $payload, "campus-report_{$code}.pdf", 'landscape');
    }

    public function staffCsvLive(CampusMonthlyReportRequest $request, CampusMonthlyReportService $reports, ReportFileService $files): StreamedResponse
    {
        [$month, $year] = $request->period();
        $payload = $reports->reportFor($request->user(), $month, $year);

        return $this->staffCsv($payload, $files, $year, $month);
    }

    public function projectsCsvLive(CampusMonthlyReportRequest $request, CampusMonthlyReportService $reports, ReportFileService $files): StreamedResponse
    {
        [$month, $year] = $request->period();
        $payload = $reports->reportFor($request->user(), $month, $year);

        return $this->projectsCsv($payload, $files, $year, $month);
    }

    public function printFinalized(CampusMonthlyReport $campusReport): View
    {
        return view('reports.campus-monthly', $this->frozen($campusReport) + ['isPdf' => false]);
    }

    public function pdfFinalized(CampusMonthlyReport $campusReport, ReportFileService $files): Response
    {
        $payload = $this->frozen($campusReport);

        return $files->pdf('reports.campus-monthly', $payload, "campus-report_{$campusReport->report_code}.pdf", 'landscape');
    }

    public function staffCsvFinalized(CampusMonthlyReport $campusReport, ReportFileService $files): StreamedResponse
    {
        return $this->staffCsv($this->frozen($campusReport), $files, $campusReport->reporting_year, $campusReport->reporting_month);
    }

    public function projectsCsvFinalized(CampusMonthlyReport $campusReport, ReportFileService $files): StreamedResponse
    {
        return $this->projectsCsv($this->frozen($campusReport), $files, $campusReport->reporting_year, $campusReport->reporting_month);
    }

    private function frozen(CampusMonthlyReport $report): array
    {
        Gate::authorize('view', $report);
        $report->loadMissing(['campus', 'finalizer:id,name']);

        return ['report' => $report, 'data' => $report->snapshot, 'isFrozen' => true];
    }

    private function staffCsv(array $payload, ReportFileService $files, int $year, int $month): StreamedResponse
    {
        $rows = collect($payload['data']['staff_rows'])->map(fn (array $row) => [
            $row['name'], $row['position'], $row['library'], $row['hours'], $row['days_reported'],
            $row['tasks_assigned'], $row['completed'], $row['in_progress'], $row['overdue'],
            $row['completion_rate'], str($row['report_status'])->replace('_', ' ')->title()->toString(),
        ]);

        return $files->csv(['Staff', 'Position', 'Library', 'Hours', 'Days Reported', 'Tasks Assigned', 'Completed', 'In Progress', 'Overdue', 'Completion Rate (%)', 'Monthly Report Status'], $rows,
            "campus-staff_{$payload['data']['identity']['campus']}_{$year}_".str_pad((string) $month, 2, '0', STR_PAD_LEFT).'.csv');
    }

    private function projectsCsv(array $payload, ReportFileService $files, int $year, int $month): StreamedResponse
    {
        $rows = collect($payload['data']['project_rows'])->map(fn (array $row) => [
            $row['code'], $row['title'], $row['status'], $row['progress'], $row['staff'],
            $row['task_count'], $row['completed'], $row['in_progress'], $row['overdue'],
        ]);

        return $files->csv(['Project Code', 'Project', 'Status', 'Progress (%)', 'Campus Staff', 'Tasks', 'Completed', 'In Progress', 'Overdue'], $rows,
            "campus-projects_{$payload['data']['identity']['campus']}_{$year}_".str_pad((string) $month, 2, '0', STR_PAD_LEFT).'.csv');
    }
}

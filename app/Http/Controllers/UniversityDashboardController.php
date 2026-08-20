<?php

namespace App\Http\Controllers;

use App\Http\Requests\UniversityDashboardRequest;
use App\Models\Campus;
use App\Services\ReportFileService;
use App\Services\UniversityDashboardService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UniversityDashboardController extends Controller
{
    public function index(UniversityDashboardRequest $request, UniversityDashboardService $dashboard): View
    {
        [$month, $year] = $request->period();

        return view('university-dashboard.index', $dashboard->dashboard($month, $year));
    }

    public function campus(UniversityDashboardRequest $request, Campus $campus, UniversityDashboardService $dashboard): View
    {
        [$month, $year] = $request->period();

        abort_unless($campus->is_active, 404);

        return view('university-dashboard.campus', $dashboard->campusDetail($campus, $month, $year));
    }

    public function csv(UniversityDashboardRequest $request, UniversityDashboardService $dashboard, ReportFileService $files): StreamedResponse
    {
        [$month, $year] = $request->period();
        $data = $dashboard->dashboard($month, $year);
        $rows = $data['campusRows']->map(fn (array $row) => [
            $row['campus']->name, $row['libraries'], $row['total_staff'], $row['active_staff'],
            round($row['minutes'] / 60, 2), $row['staff_reporting'], $row['assigned'], $row['completed'],
            $row['in_progress'], $row['overdue'], $row['completion_rate'], $row['active_projects'],
            $row['report']?->report_code, $row['report'] ? 'Finalized' : 'Not Finalized',
        ]);

        return $files->csv(['Campus', 'Libraries', 'Total Staff', 'Active Staff', 'Hours', 'Staff Reporting', 'Tasks Assigned', 'Completed', 'In Progress', 'Overdue', 'Completion Rate (%)', 'Active Projects', 'CMR Code', 'Campus Report Status'], $rows,
            "university-performance_{$year}_".str_pad((string) $month, 2, '0', STR_PAD_LEFT).'.csv');
    }
}

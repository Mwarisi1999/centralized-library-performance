<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonthlyReportPeriodRequest;
use App\Services\IndividualMonthlyReportService;
use App\Services\ReportFileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class IndividualMonthlyReportExportController extends Controller
{
    public function print(MonthlyReportPeriodRequest $request, IndividualMonthlyReportService $reports): View
    {
        [$month, $year] = $this->period($request);

        return view('reports.individual-monthly', $reports->foundationFor($request->user(), $month, $year) + ['isPdf' => false]);
    }

    public function pdf(MonthlyReportPeriodRequest $request, IndividualMonthlyReportService $reports, ReportFileService $files): Response
    {
        [$month, $year] = $this->period($request);
        $data = $reports->foundationFor($request->user(), $month, $year);
        $identity = $data['report']?->report_code ?? "draft-{$year}-{$month}";

        return $files->pdf('reports.individual-monthly', $data, "monthly-report_{$identity}.pdf");
    }

    private function period(MonthlyReportPeriodRequest $request): array
    {
        $period = $request->validated();

        return [(int) $period['month'], (int) $period['year']];
    }
}

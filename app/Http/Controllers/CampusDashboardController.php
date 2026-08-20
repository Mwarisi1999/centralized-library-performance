<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampusDashboardRequest;
use App\Models\User;
use App\Services\CampusDashboardService;
use App\Services\IndividualMonthlyReportService;
use Illuminate\View\View;

class CampusDashboardController extends Controller
{
    public function index(CampusDashboardRequest $request, CampusDashboardService $dashboard): View
    {
        [$month, $year] = $request->period();

        return view('campus-dashboard.index', $dashboard->dashboardFor($request->user(), $month, $year));
    }

    public function staff(CampusDashboardRequest $request, User $staff, CampusDashboardService $dashboard, IndividualMonthlyReportService $reports): View
    {
        [$month, $year] = $request->period();

        return view('campus-dashboard.staff', $dashboard->staffDetailFor($request->user(), $staff, $month, $year, $reports));
    }
}

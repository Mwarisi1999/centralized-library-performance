<?php

namespace App\Http\Controllers;

use App\Services\IndividualDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, IndividualDashboardService $dashboard): View
    {
        $user = $request->user();
        $taskMetrics = $dashboard->taskMetricsFor($user);

        return view('dashboard', [
            'summary' => $dashboard->summaryFor($user, $taskMetrics),
            'activeProjects' => $dashboard->activeProjectsFor($user),
            'upcomingDeadlines' => $dashboard->upcomingDeadlinesFor($user),
            'upcomingDeadlineDays' => IndividualDashboardService::UPCOMING_DEADLINE_DAYS,
            'chartData' => $dashboard->chartDataFor($user, $taskMetrics),
            'recentActivity' => $dashboard->recentActivityFor($user),
            'alerts' => $dashboard->alertsFor($user),
        ]);
    }
}

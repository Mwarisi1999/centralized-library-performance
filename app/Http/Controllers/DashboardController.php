<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\MonthlyReport;
use App\Models\Task;
use App\Models\User;
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
            'notifications' => $user->unreadNotifications()->latest()->limit(5)->get(),
            'adminSummary' => $user->hasRole('Administrator') ? [
                'users' => User::count(),
                'inactive_accounts' => User::where('account_status', '!=', 'active')->count(),
                'active_campuses' => Campus::where('is_active', true)->count(),
                'pending_task_reviews' => Task::where('status', 'pending_review')->count(),
                'pending_reports' => MonthlyReport::where('status', MonthlyReport::STATUS_PENDING_REVIEW)->count(),
            ] : null,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\MonthlyReport;
use App\Models\Task;
use App\Models\User;
use App\Services\IndividualDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function __invoke(Request $request, IndividualDashboardService $dashboard): View
    {
        $user = $request->user();
        $taskMetrics = $dashboard->taskMetricsFor($user);
        $isAdministrator = $user->hasRole('Administrator');

        return view('dashboard', [
            'summary' => $dashboard->summaryFor($user, $taskMetrics),
            'activeProjects' => $dashboard->activeProjectsFor($user),
            'upcomingDeadlines' => $dashboard->upcomingDeadlinesFor($user),
            'upcomingDeadlineDays' => IndividualDashboardService::UPCOMING_DEADLINE_DAYS,
            'chartData' => $dashboard->chartDataFor($user, $taskMetrics),
            'recentActivity' => $dashboard->recentActivityFor($user),
            'alerts' => $dashboard->alertsFor($user),
            'notifications' => $user->unreadNotifications()->latest()->limit(5)->get(),
            'adminCharts' => $isAdministrator ? $this->adminChartData() : null,
            'adminSummary' => $isAdministrator ? [
                'users' => User::count(),
                'inactive_accounts' => User::where('account_status', '!=', 'active')->count(),
                'active_campuses' => Campus::where('is_active', true)->count(),
                'pending_task_reviews' => Task::where('status', 'pending_review')->count(),
                'pending_reports' => MonthlyReport::where('status', MonthlyReport::STATUS_PENDING_REVIEW)->count(),
            ] : null,
        ]);
    }

    /**
     * Organisation-wide breakdowns shown only on the administrator dashboard.
     *
     * @return array<string, array{labels: list<string>, values: list<int>}>
     */
    private function adminChartData(): array
    {
        $accountStatuses = User::query()
            ->select('account_status', DB::raw('count(*) as total'))
            ->groupBy('account_status')
            ->pluck('total', 'account_status');

        $usersByRole = DB::table(config('permission.table_names.roles'))
            ->leftJoin(config('permission.table_names.model_has_roles').' as assigned', function ($join) {
                $join->on('assigned.role_id', '=', config('permission.table_names.roles').'.id')
                    ->where('assigned.model_type', (new User)->getMorphClass());
            })
            ->select(config('permission.table_names.roles').'.name', DB::raw('count(assigned.model_id) as total'))
            ->groupBy(config('permission.table_names.roles').'.name')
            ->orderByDesc('total')
            ->pluck('total', 'name');

        $taskStatuses = Task::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'account_status' => [
                'labels' => $accountStatuses->keys()->map(fn (string $status) => Str::headline($status))->values()->all(),
                'values' => $accountStatuses->values()->map(fn ($total) => (int) $total)->all(),
            ],
            'users_by_role' => [
                'labels' => $usersByRole->keys()->values()->all(),
                'values' => $usersByRole->values()->map(fn ($total) => (int) $total)->all(),
            ],
            'task_pipeline' => [
                'labels' => collect(Task::STATUSES)->map(fn (string $status) => Task::label($status))->all(),
                'values' => collect(Task::STATUSES)->map(fn (string $status) => (int) ($taskStatuses[$status] ?? 0))->all(),
            ],
        ];
    }
}

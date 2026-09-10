<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\CampusMonthlyReportActivity;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportActivity;
use App\Models\StaffProfile;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Models\WorkEntryActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdministrationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole('Administrator') && $request->user()->can('view administration'), 403);

        $roles = Role::with('permissions')->withCount('users')->orderBy('name')->get();
        $users = User::with(['roles', 'staffProfile.campus', 'staffProfile.library'])->orderBy('name')->paginate(20, ['*'], 'users');
        $activity = $this->activity();
        $campusCount = Campus::where('is_active', true)->count();

        return view('admin.administration.index', [
            'roles' => $roles,
            'permissions' => Permission::orderBy('name')->get(),
            'users' => $users,
            'activity' => $activity,
            'health' => [
                'pending_task_reviews' => Task::where('status', 'pending_review')->count(),
                'pending_reports' => MonthlyReport::where('status', MonthlyReport::STATUS_PENDING_REVIEW)->count(),
                'returned_reports' => MonthlyReport::where('status', MonthlyReport::STATUS_RETURNED_FOR_CORRECTION)->count(),
                'inactive_accounts' => User::where('account_status', '!=', 'active')->count(),
                'inactive_profiles' => StaffProfile::where('status', '!=', 'active')->count(),
                'active_campuses' => $campusCount,
            ],
            'system' => [
                'application' => config('app.name'),
                'environment' => app()->environment(),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'database' => config('database.default'),
            ],
        ]);
    }

    private function activity(): Collection
    {
        $map = fn ($rows, $entity, $action, $description) => $rows->map(fn ($row) => [
            'actor' => $row->user?->name ?? 'System', 'entity' => $entity, 'action' => data_get($row, $action),
            'description' => data_get($row, $description), 'at' => $row->created_at,
        ]);

        return $map(TaskActivity::with('user:id,name')->latest()->limit(25)->get(), 'Task', 'activity_type', 'message')
            ->concat($map(WorkEntryActivity::with('user:id,name')->latest()->limit(25)->get(), 'Work Entry', 'event', 'description'))
            ->concat($map(MonthlyReportActivity::with('user:id,name')->latest()->limit(25)->get(), 'Monthly Report', 'event', 'description'))
            ->concat($map(CampusMonthlyReportActivity::with('user:id,name')->latest()->limit(25)->get(), 'Campus Report', 'event', 'description'))
            ->sortByDesc('at')->take(50)->values();
    }
}

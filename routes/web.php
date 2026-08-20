<?php

use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\AccountActivationController;
use App\Http\Controllers\CampusDashboardController;
use App\Http\Controllers\CampusMonthlyReportController;
use App\Http\Controllers\CampusMonthlyReportExportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IndividualMonthlyReportController;
use App\Http\Controllers\IndividualMonthlyReportExportController;
use App\Http\Controllers\MonthlyReportReviewController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimesheetReportController;
use App\Http\Controllers\UniversityDashboardController;
use App\Http\Controllers\WorkEntryController;
use App\Http\Controllers\WorkEvidenceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'active'])
    ->name('dashboard');

Route::middleware(['guest', 'throttle:6,1'])->group(function () {
    Route::get('/activate-account/{token}', [AccountActivationController::class, 'create'])
        ->name('account.activate');

    Route::post('/activate-account/{token}', [AccountActivationController::class, 'store'])
        ->name('account.activate.store');
});

Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/university-dashboard', [UniversityDashboardController::class, 'index'])->name('university-dashboard.index');
    Route::get('/university-dashboard/export.csv', [UniversityDashboardController::class, 'csv'])->name('university-dashboard.csv');
    Route::get('/university-dashboard/campuses/{campus}', [UniversityDashboardController::class, 'campus'])->name('university-dashboard.campus');

    Route::get('/campus-dashboard', [CampusDashboardController::class, 'index'])->name('campus-dashboard.index');
    Route::get('/campus-dashboard/staff/{staff}', [CampusDashboardController::class, 'staff'])->name('campus-dashboard.staff');

    Route::get('/campus-reports', [CampusMonthlyReportController::class, 'index'])->name('campus-reports.index');
    Route::get('/campus-reports/create', [CampusMonthlyReportController::class, 'create'])->name('campus-reports.create');
    Route::get('/campus-reports/print/live', [CampusMonthlyReportExportController::class, 'printLive'])->name('campus-reports.print.live');
    Route::get('/campus-reports/pdf/live', [CampusMonthlyReportExportController::class, 'pdfLive'])->name('campus-reports.pdf.live');
    Route::get('/campus-reports/staff.csv/live', [CampusMonthlyReportExportController::class, 'staffCsvLive'])->name('campus-reports.staff-csv.live');
    Route::get('/campus-reports/projects.csv/live', [CampusMonthlyReportExportController::class, 'projectsCsvLive'])->name('campus-reports.projects-csv.live');
    Route::post('/campus-reports/finalize', [CampusMonthlyReportController::class, 'finalize'])->name('campus-reports.finalize');
    Route::get('/campus-reports/{campusReport}', [CampusMonthlyReportController::class, 'show'])->name('campus-reports.show');
    Route::get('/campus-reports/{campusReport}/print', [CampusMonthlyReportExportController::class, 'printFinalized'])->name('campus-reports.print');
    Route::get('/campus-reports/{campusReport}/pdf', [CampusMonthlyReportExportController::class, 'pdfFinalized'])->name('campus-reports.pdf');
    Route::get('/campus-reports/{campusReport}/staff.csv', [CampusMonthlyReportExportController::class, 'staffCsvFinalized'])->name('campus-reports.staff-csv');
    Route::get('/campus-reports/{campusReport}/projects.csv', [CampusMonthlyReportExportController::class, 'projectsCsvFinalized'])->name('campus-reports.projects-csv');

    Route::get('/my-work', [WorkEntryController::class, 'index'])->name('my-work.index');
    Route::get('/my-work/timesheet', [WorkEntryController::class, 'timesheet'])->name('my-work.timesheet');
    Route::get('/my-work/timesheet/print', [TimesheetReportController::class, 'print'])->name('my-work.timesheet.print');
    Route::get('/my-work/timesheet/pdf', [TimesheetReportController::class, 'pdf'])->name('my-work.timesheet.pdf');
    Route::get('/my-work/timesheet/export.csv', [TimesheetReportController::class, 'csv'])->name('my-work.timesheet.csv');
    Route::get('/my-work/monthly-report', IndividualMonthlyReportController::class)->name('my-work.monthly-report');
    Route::get('/my-work/monthly-report/print', [IndividualMonthlyReportExportController::class, 'print'])->name('my-work.monthly-report.print');
    Route::get('/my-work/monthly-report/pdf', [IndividualMonthlyReportExportController::class, 'pdf'])->name('my-work.monthly-report.pdf');
    Route::post('/my-work/monthly-report/submit', [IndividualMonthlyReportController::class, 'submit'])->name('my-work.monthly-report.submit');
    Route::get('/monthly-reports/reviews', [MonthlyReportReviewController::class, 'index'])->name('monthly-reports.reviews.index');
    Route::get('/monthly-reports/{monthlyReport}/review', [MonthlyReportReviewController::class, 'show'])->name('monthly-reports.reviews.show');
    Route::post('/monthly-reports/{monthlyReport}/approve', [MonthlyReportReviewController::class, 'approve'])->name('monthly-reports.approve');
    Route::post('/monthly-reports/{monthlyReport}/return', [MonthlyReportReviewController::class, 'returnForCorrection'])->name('monthly-reports.return');
    Route::get('/my-work/entries/create', [WorkEntryController::class, 'create'])->name('work-entries.create');
    Route::post('/my-work/entries', [WorkEntryController::class, 'store'])->name('work-entries.store');
    Route::get('/my-work/entries/{workEntry}/edit', [WorkEntryController::class, 'edit'])->name('work-entries.edit');
    Route::match(['put', 'patch'], '/my-work/entries/{workEntry}', [WorkEntryController::class, 'update'])->name('work-entries.update');
    Route::get('/my-work/entries/{workEntry}', [WorkEntryController::class, 'show'])->name('work-entries.show');
    Route::get('/my-work/entries/{workEntry}/evidence/create', [WorkEvidenceController::class, 'create'])->name('work-entries.evidence.create');
    Route::post('/my-work/entries/{workEntry}/evidence', [WorkEvidenceController::class, 'store'])->name('work-entries.evidence.store');
    Route::get('/my-work/evidence/{workEvidence}', [WorkEvidenceController::class, 'show'])->name('work-evidence.show');
    Route::get('/my-work/evidence/{workEvidence}/download', [WorkEvidenceController::class, 'download'])->name('work-evidence.download');
    Route::delete('/my-work/evidence/{workEvidence}', [WorkEvidenceController::class, 'destroy'])->name('work-evidence.destroy');

    Route::middleware('permission:view projects')->group(function () {
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    });

    Route::middleware('permission:create projects')->group(function () {
        Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    });

    Route::middleware('permission:edit projects')->group(function () {
        Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::match(['put', 'patch'], '/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    });

    Route::get('/projects/{project}', [ProjectController::class, 'show'])
        ->middleware('permission:view projects')
        ->name('projects.show');

    Route::middleware('permission:view tasks')->group(function () {
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    });

    Route::middleware('permission:create tasks')->group(function () {
        Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    });

    Route::get('/tasks/{task}', [TaskController::class, 'show'])
        ->middleware('permission:view tasks')
        ->name('tasks.show');

    Route::post('/tasks/{task}/start', [TaskController::class, 'start'])->name('tasks.start');
    Route::post('/tasks/{task}/progress', [TaskController::class, 'updateProgress'])->name('tasks.progress');
    Route::post('/tasks/{task}/submit-review', [TaskController::class, 'submitReview'])->name('tasks.submit-review');
    Route::post('/tasks/{task}/approve', [TaskController::class, 'approve'])->name('tasks.approve');
    Route::post('/tasks/{task}/return-correction', [TaskController::class, 'returnForCorrection'])->name('tasks.return-correction');
    Route::get('/tasks/{task}/subtasks/create', [SubtaskController::class, 'create'])->name('tasks.subtasks.create');
    Route::post('/tasks/{task}/subtasks', [SubtaskController::class, 'store'])->name('tasks.subtasks.store');
    Route::get('/subtasks/{subtask}', [SubtaskController::class, 'show'])->name('subtasks.show');
    Route::post('/subtasks/{subtask}/start', [SubtaskController::class, 'start'])->name('subtasks.start');
    Route::patch('/subtasks/{subtask}/progress', [SubtaskController::class, 'updateProgress'])->name('subtasks.progress');
    Route::post('/subtasks/{subtask}/complete', [SubtaskController::class, 'complete'])->name('subtasks.complete');

    Route::get('/admin/staff', [StaffController::class, 'index'])
        ->middleware(['verified', 'permission:view staff'])
        ->name('admin.staff.index');

    Route::middleware('permission:create staff')->group(function () {

        Route::get('/admin/staff/create', [StaffController::class, 'create'])
            ->name('admin.staff.create');

        Route::post('/admin/staff', [StaffController::class, 'store'])
            ->name('admin.staff.store');

        Route::post('/admin/staff/{user}/resend-invitation', [StaffController::class, 'resendInvitation'])
            ->middleware('verified')
            ->name('admin.staff.resend-invitation');

    });

    Route::middleware(['verified', 'permission:edit staff'])->group(function () {
        Route::get('/admin/staff/{user}/edit', [StaffController::class, 'edit'])
            ->name('admin.staff.edit');

        Route::match(['put', 'patch'], '/admin/staff/{user}', [StaffController::class, 'update'])
            ->name('admin.staff.update');
    });

    Route::get('/admin/staff/{user}', [StaffController::class, 'show'])
        ->middleware(['verified', 'permission:view staff'])
        ->name('admin.staff.show');

});

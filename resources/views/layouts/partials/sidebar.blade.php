@php
    $user = auth()->user();
    $permissions = $user->getAllPermissions()->pluck('name');
    $hasAnyPermission = fn (array $names) => $permissions->intersect($names)->isNotEmpty();
    $canSeeMyWork = $hasAnyPermission([
        'view own tasks', 'view supervised tasks', 'create timesheet entries',
        'view own timesheet', 'view supervised timesheets', 'view all timesheets',
    ]);
    $canSeeTasks = $hasAnyPermission(['view tasks', 'view own tasks', 'view supervised tasks']);
    $canSeeReports = $hasAnyPermission([
        'view own reports', 'view supervised reports', 'view all reports', 'submit reports',
        'review reports', 'approve reports', 'return reports', 'reopen reports',
    ]);
    $canReviewReports = $hasAnyPermission(['view supervised reports', 'review reports', 'approve reports', 'return reports']);
    $canSeeEvidence = $hasAnyPermission(['upload evidence', 'view supervised evidence', 'view all evidence']);
    $canSeeOrganization = $hasAnyPermission(['manage campuses', 'manage libraries', 'manage positions']);
    $isStaffOrIntern = $user->hasAnyRole(['Staff', 'Intern']);
@endphp

<aside id="app-sidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col overflow-hidden bg-busitema-blue text-white shadow-2xl transition-transform duration-200 ease-out lg:inset-y-2 lg:left-1 lg:translate-x-0 lg:rounded-2xl" aria-label="Main navigation">
    <div class="flex h-24 shrink-0 items-center justify-between gap-3 border-b border-white/15 px-4">
        <a data-sidebar-logo href="{{ route('dashboard') }}" class="flex min-w-0 flex-1 items-center justify-center rounded-xl bg-white px-3 py-2 shadow-sm" aria-label="Busitema University Library Performance System home">
            <img
                src="{{ asset('images/branding/busitema-university-logo.png') }}"
                alt="Busitema University"
                class="max-h-12 w-auto max-w-full object-contain"
            >
        </a>
        <button type="button" data-sidebar-close class="shrink-0 rounded-lg p-2 text-white/70 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Close navigation">
            <span aria-hidden="true" class="text-2xl leading-none">&times;</span>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5">
        <p data-sidebar-section class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white/60">Overview</p>
        @include('layouts.partials.sidebar-item', ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')])

        @if($user->hasRole('University Librarian') && $permissions->contains('view university dashboard'))
            @include('layouts.partials.sidebar-item', ['label' => 'University Dashboard', 'route' => 'university-dashboard.index', 'active' => request()->routeIs('university-dashboard.*')])
        @endif

        @if($user->hasRole('Campus Librarian') && $permissions->contains('view campus dashboard'))
            @include('layouts.partials.sidebar-item', ['label' => 'Campus Dashboard', 'route' => 'campus-dashboard.index', 'active' => request()->routeIs('campus-dashboard.*')])
        @endif
        @if($user->hasRole('Campus Librarian') && $permissions->contains('view campus reports'))
            @include('layouts.partials.sidebar-item', ['label' => 'Campus Monthly Reports', 'route' => 'campus-reports.index', 'active' => request()->routeIs('campus-reports.*')])
        @endif

        <p data-sidebar-section class="px-3 pb-2 pt-5 text-[10px] font-bold uppercase tracking-[0.2em] text-white/60">Work Management</p>
        @if($isStaffOrIntern)
            @include('layouts.partials.sidebar-item', ['label' => 'Daily Activities', 'route' => 'daily-activities.index', 'active' => request()->routeIs('daily-activities.*') || request()->routeIs('my-work.index') || request()->routeIs('work-entries.*')])
            @include('layouts.partials.sidebar-item', ['label' => 'Weekly Activities', 'route' => 'weekly-activities.index', 'active' => request()->routeIs('weekly-activities.*')])
        @elseif($canSeeMyWork)
            @include('layouts.partials.sidebar-item', ['label' => 'My Work', 'route' => 'my-work.index', 'active' => (request()->routeIs('my-work.*') && ! request()->routeIs('my-work.monthly-report*')) || request()->routeIs('work-entries.*')])
        @endif
        @if($permissions->contains('view projects'))
            @include('layouts.partials.sidebar-item', ['label' => 'Projects', 'route' => 'projects.index', 'active' => request()->routeIs('projects.*')])
        @endif
        @if($isStaffOrIntern)
            @include('layouts.partials.sidebar-item', ['label' => 'Task Tracker', 'route' => 'task-tracker.index', 'active' => request()->routeIs('task-tracker.*') || request()->routeIs('tasks.show') || request()->routeIs('subtasks.*')])
        @elseif($canSeeTasks)
            @include('layouts.partials.sidebar-item', ['label' => 'Tasks', 'route' => 'tasks.index', 'active' => request()->routeIs('tasks.*')])
        @endif
        @if($canSeeEvidence)
            @include('layouts.partials.sidebar-item', ['label' => 'Evidence'])
        @endif

        <p data-sidebar-section class="px-3 pb-2 pt-5 text-[10px] font-bold uppercase tracking-[0.2em] text-white/60">Performance</p>
        @if($canSeeReports)
            @include('layouts.partials.sidebar-item', ['label' => 'Reports', 'route' => 'my-work.monthly-report', 'active' => request()->routeIs('my-work.monthly-report*')])
        @endif
        @if($isStaffOrIntern)
            @include('layouts.partials.sidebar-item', ['label' => 'Printable Timesheet', 'route' => 'printable-timesheet.index', 'active' => request()->routeIs('printable-timesheet.*') || request()->routeIs('my-work.timesheet.print')])
            @include('layouts.partials.sidebar-item', ['label' => 'Profile', 'route' => 'profile.show', 'active' => request()->routeIs('profile.*')])
        @endif
        @if($canReviewReports)
            @include('layouts.partials.sidebar-item', ['label' => 'Reports Awaiting My Review', 'route' => 'monthly-reports.reviews.index', 'active' => request()->routeIs('monthly-reports.*')])
        @endif
        @if($permissions->contains('view staff'))
            <div class="pt-5">
                <p data-sidebar-section class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white/60">Management</p>
                @include('layouts.partials.sidebar-item', ['label' => 'Staff Management', 'route' => 'admin.staff.index', 'active' => request()->routeIs('admin.staff.*')])
            </div>
        @endif
        @if($canSeeOrganization)
            @include('layouts.partials.sidebar-item', ['label' => 'Organization Setup'])
        @endif
        @if($permissions->contains('manage roles and permissions'))
            @include('layouts.partials.sidebar-item', ['label' => 'Administration'])
        @endif
    </nav>

    <div data-sidebar-user class="border-t border-white/15 px-5 py-3">
        <p class="truncate text-xs font-semibold text-white">{{ $user->name }}</p>
        <p class="mt-1 truncate text-[11px] text-white/60">{{ $user->getRoleNames()->join(', ') ?: 'User' }}</p>
    </div>

    <button type="button" data-sidebar-collapse class="hidden min-h-16 shrink-0 items-center gap-3 border-t border-white/15 px-6 text-left text-sm font-semibold text-white transition hover:bg-white/10 lg:flex" aria-pressed="false" title="Collapse sidebar">
        <svg data-collapse-chevron class="h-4 w-4 shrink-0 transition-transform duration-200" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M12.5 4.5 7 10l5.5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span data-collapse-label>Collapse sidebar</span>
    </button>
</aside>

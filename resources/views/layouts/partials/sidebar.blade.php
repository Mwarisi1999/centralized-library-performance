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
@endphp

<aside id="app-sidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-slate-950 text-slate-200 shadow-2xl transition-transform duration-200 ease-out lg:translate-x-0" aria-label="Main navigation">
    <div class="flex h-20 shrink-0 items-center justify-between border-b border-white/10 px-5">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-700 text-sm font-extrabold tracking-wide text-white shadow-lg shadow-emerald-950/40">BU</span>
            <span class="min-w-0">
                <span class="block truncate text-sm font-bold text-white">Busitema University</span>
                <span class="mt-0.5 block text-[11px] font-medium leading-4 text-slate-400">Library Performance System</span>
            </span>
        </a>
        <button type="button" data-sidebar-close class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Close navigation">
            <span aria-hidden="true" class="text-2xl leading-none">&times;</span>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5">
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

        @if($canSeeMyWork)
            @include('layouts.partials.sidebar-item', ['label' => 'My Work', 'route' => 'my-work.index', 'active' => (request()->routeIs('my-work.*') && ! request()->routeIs('my-work.monthly-report*')) || request()->routeIs('work-entries.*')])
        @endif
        @if($permissions->contains('view projects'))
            @include('layouts.partials.sidebar-item', ['label' => 'Projects', 'route' => 'projects.index', 'active' => request()->routeIs('projects.*')])
        @endif
        @if($canSeeTasks)
            @include('layouts.partials.sidebar-item', ['label' => 'Tasks', 'route' => 'tasks.index', 'active' => request()->routeIs('tasks.*')])
        @endif
        @if($canSeeReports)
            @include('layouts.partials.sidebar-item', ['label' => 'Reports', 'route' => 'my-work.monthly-report', 'active' => request()->routeIs('my-work.monthly-report*')])
        @endif
        @if($canReviewReports)
            @include('layouts.partials.sidebar-item', ['label' => 'Reports Awaiting My Review', 'route' => 'monthly-reports.reviews.index', 'active' => request()->routeIs('monthly-reports.*')])
        @endif
        @if($canSeeEvidence)
            @include('layouts.partials.sidebar-item', ['label' => 'Evidence'])
        @endif

        @if($permissions->contains('view staff'))
            <div class="pt-4">
                <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Management</p>
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

    <div class="border-t border-white/10 px-5 py-4">
        <p class="truncate text-xs font-semibold text-slate-300">{{ $user->name }}</p>
        <p class="mt-1 truncate text-[11px] text-slate-500">{{ $user->getRoleNames()->join(', ') ?: 'User' }}</p>
    </div>
</aside>

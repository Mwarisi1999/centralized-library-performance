@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    @php
        $user = auth()->user();
        $user->loadMissing('staffProfile.position.jobDetail');
        $assignedPosition = $user->staffProfile?->position;
        $jobDetail = $assignedPosition?->jobDetail;
        $isAdministrator = $user->hasRole('Administrator');
    @endphp
    @if($isAdministrator)
        @php
            $activeAccounts = max(0, $adminSummary['users'] - $adminSummary['inactive_accounts']);
            $activeShare = $adminSummary['users'] > 0 ? round($activeAccounts / $adminSummary['users'] * 100) : 0;
            $awaitingReview = $adminSummary['pending_task_reviews'] + $adminSummary['pending_reports'];
        @endphp
    @endif

    <x-dashboard.hero
        :eyebrow="$isAdministrator ? 'Administrator console' : 'Time Sheet System'"
        :title="'Welcome, '.$user->name"
        heading-id="welcome-heading"
        :description="'You are signed in as '.($user->getRoleNames()->join(', ') ?: 'User').'.'.($isAdministrator ? ' Manage access, organization data, and application governance from one place.' : '')"
    >
        @if($isAdministrator)
            <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/20">{{ $activeShare }}% accounts active</span>
            <span @class([
                'rounded-full px-3 py-1 ring-1',
                'bg-busitema-gold text-busitema-navy ring-busitema-gold' => $awaitingReview > 0,
                'bg-white/10 ring-white/20' => $awaitingReview === 0,
            ])>{{ $awaitingReview }} {{ Str::plural('item', $awaitingReview) }} awaiting review</span>
        @else
            <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/20">{{ now()->format('l, d F Y') }}</span>
        @endif
        <x-slot:actions>
            @if($isAdministrator)
                <nav class="flex flex-wrap gap-2" aria-label="Administrator quick actions">
                    <a href="{{ route('admin.staff.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/25 transition hover:bg-white/20">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                        Staff Management
                    </a>
                    <a href="{{ route('admin.organization.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/25 transition hover:bg-white/20">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
                        Organization Setup
                    </a>
                    <a href="{{ route('admin.administration.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-busitema-gold px-4 py-2.5 text-sm font-bold text-busitema-navy shadow-sm transition hover:bg-busitema-yellow">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                        Administration
                    </a>
                </nav>
            @else
                @php
                    $heroLinks = array_filter([
                        $user->hasRole('University Librarian') && $user->can('view university dashboard') ? ['University Dashboard', route('university-dashboard.index')] : null,
                        $user->hasRole('Campus Librarian') && $user->can('view campus dashboard') ? ['Campus Dashboard', route('campus-dashboard.index')] : null,
                        $user->hasAnyRole(['Staff', 'Intern']) ? ['Daily Activities', route('daily-activities.index')] : null,
                        $user->hasAnyRole(['Staff', 'Intern']) ? ['Task Tracker', route('task-tracker.index')] : null,
                    ]);
                @endphp
                @foreach(array_values($heroLinks) as $index => [$label, $url])
                    <a href="{{ $url }}" @class([
                        'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm transition',
                        'bg-busitema-gold font-bold text-busitema-navy shadow-sm hover:bg-busitema-yellow' => $index === 0,
                        'bg-white/10 font-semibold text-white ring-1 ring-white/25 hover:bg-white/20' => $index > 0,
                    ])>{{ $label }} <span aria-hidden="true">&rarr;</span></a>
                @endforeach
            @endif
        </x-slot:actions>
    </x-dashboard.hero>

    @if($jobDetail)
        <section class="mt-6 flex flex-col gap-4 rounded-2xl border border-blue-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6" aria-labelledby="current-jd-heading">
            <div class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 font-extrabold text-busitema-blue" aria-hidden="true">JD</span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-busitema-blue">Your current job description</p>
                    <h2 id="current-jd-heading" class="mt-1 text-xl font-bold text-slate-950">{{ $assignedPosition->name }}</h2>
                    <p class="mt-1 text-sm text-slate-600">Review your job purpose and {{ count($jobDetail->duties) }} assigned {{ Str::plural('duty', count($jobDetail->duties)) }} before recording your work.</p>
                </div>
            </div>
            <a href="{{ route('job-description.show') }}" class="inline-flex shrink-0 justify-center rounded-xl bg-busitema-blue px-5 py-2.5 text-sm font-bold text-white transition hover:bg-busitema-deep-blue">View my job description</a>
        </section>
    @endif

    @if($notifications->isNotEmpty())
        <section class="mt-6 overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm" aria-labelledby="notifications-heading"><div class="flex items-center justify-between border-b border-emerald-100 bg-emerald-50 px-5 py-4"><div><h2 id="notifications-heading" class="text-lg font-bold text-emerald-950">Notifications requiring attention</h2><p class="mt-1 text-sm text-emerald-800">Your latest unread workflow updates.</p></div><a href="{{ route('notifications.index') }}" class="text-sm font-semibold text-emerald-800">View all</a></div><div class="divide-y divide-slate-100">@foreach($notifications as $notification)<form method="POST" action="{{ route('notifications.read',$notification) }}" class="flex items-start justify-between gap-4 p-4 sm:px-5">@csrf<div><p class="font-semibold">{{ data_get($notification->data,'title') }}</p><p class="mt-1 text-sm text-slate-600">{{ data_get($notification->data,'message') }}</p><p class="mt-1 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</p></div><button class="shrink-0 text-sm font-semibold text-emerald-700">Open</button></form>@endforeach</div></section>
    @endif

    @if(auth()->user()->hasRole('Administrator'))
        <section class="mt-6 space-y-6" aria-label="Organisation overview">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-dashboard.stat-card label="Users" :value="number_format($adminSummary['users'])" icon="users" tone="blue" :href="route('admin.staff.index')" note="Registered accounts">
                    <div class="flex items-center justify-between text-xs font-semibold">
                        <span class="text-busitema-blue">{{ $activeAccounts }} active</span>
                        <span @class(['text-red-600' => $adminSummary['inactive_accounts'] > 0, 'text-slate-500' => $adminSummary['inactive_accounts'] === 0])>{{ $adminSummary['inactive_accounts'] }} inactive</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-busitema-blue" style="width: {{ $activeShare }}%"></div></div>
                </x-dashboard.stat-card>
                <x-dashboard.stat-card label="Active Campuses" :value="number_format($adminSummary['active_campuses'])" icon="campus" tone="navy" :href="route('admin.organization.index')" note="Campuses currently operating" />
                <x-dashboard.stat-card label="Pending Task Reviews" :value="number_format($adminSummary['pending_task_reviews'])" icon="review" :tone="$adminSummary['pending_task_reviews'] > 0 ? 'gold' : 'navy'" :note="$adminSummary['pending_task_reviews'] > 0 ? 'Tasks waiting for supervisor sign-off' : 'Review queue is clear'" />
                <x-dashboard.stat-card label="Pending Reports" :value="number_format($adminSummary['pending_reports'])" icon="report" :tone="$adminSummary['pending_reports'] > 0 ? 'gold' : 'navy'" :note="$adminSummary['pending_reports'] > 0 ? 'Monthly reports awaiting review' : 'No reports awaiting review'" />
            </div>

            <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h3 class="text-lg font-bold">Account Status</h3>
                    <p class="mt-1 text-sm text-slate-500">All user accounts by lifecycle status.</p>
                    <div id="admin-account-status-chart" class="mt-4 min-h-72" role="img" aria-label="Account status chart"></div>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h3 class="text-lg font-bold">Users by Role</h3>
                    <p class="mt-1 text-sm text-slate-500">How access is distributed across roles.</p>
                    <div id="admin-users-by-role-chart" class="mt-4 min-h-72" role="img" aria-label="Users by role chart"></div>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:col-span-2 xl:col-span-1">
                    <h3 class="text-lg font-bold">Organisation Task Pipeline</h3>
                    <p class="mt-1 text-sm text-slate-500">Every task in the system by current status.</p>
                    <div id="admin-task-pipeline-chart" class="mt-4 min-h-72" role="img" aria-label="Organisation task pipeline chart"></div>
                </article>
            </div>

            <script id="admin-dashboard-chart-data" type="application/json">@json($adminCharts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
        </section>
    @endif

    @if(auth()->user()->hasAnyRole(['Staff', 'Intern']))
        <section class="mt-6" aria-labelledby="work-modules-heading">
            <div class="mb-4"><h2 id="work-modules-heading" class="text-xl font-bold">Your work modules</h2><p class="mt-1 text-sm text-slate-600">Record activities, monitor assignments, and prepare your timesheet.</p></div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                @foreach([
                    ['Daily Activities', 'Record and review work completed each day.', 'daily-activities.index'],
                    ['Weekly Activities', 'See activity coverage from Monday through Sunday.', 'weekly-activities.index'],
                    ['Task Tracker', 'Monitor assigned tasks, deadlines, progress, and hours.', 'task-tracker.index'],
                    ['Printable Timesheet', 'Prepare monthly work records for printing or export.', 'printable-timesheet.index'],
                    ['Profile', 'Manage your personal details, photo, and account security.', 'profile.show'],
                ] as [$label, $description, $route])
                    <a href="{{ route($route) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-600 hover:shadow-md">
                        <span class="block h-2 w-2 rounded-full bg-amber-400"></span>
                        <h3 class="mt-4 text-lg font-bold group-hover:text-blue-700">{{ $label }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
                        <span class="mt-4 inline-block text-sm font-semibold text-blue-700">Open module →</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-8" aria-labelledby="personal-summary-heading">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 id="personal-summary-heading" class="text-xl font-bold text-slate-900">Your work summary</h2>
                <p class="mt-1 text-sm text-slate-600">A personal overview based only on your projects, assignments, and work entries.</p>
            </div>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">{{ now()->format('F Y') }}</span>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-dashboard.gauge-card chart-id="completion-rate-chart" :rate="$summary['completion_rate']" class="sm:col-span-2 lg:col-span-1 lg:row-span-2">
                <span class="font-bold text-heading">{{ $summary['completed_tasks'] }}</span> of
                <span class="font-bold text-heading">{{ $summary['assigned_tasks'] }}</span> assigned {{ Str::plural('task', $summary['assigned_tasks']) }} completed
            </x-dashboard.gauge-card>

            @php [$hoursValue, $hoursUnit] = array_pad(explode(' ', $summary['hours_this_month'], 2), 2, ''); @endphp
            <x-dashboard.highlight-card label="This Month" :value="$hoursValue" :unit="$hoursUnit" note="Recorded time this calendar month">
                <strong class="font-bold">{{ $summary['days_reported'] }}</strong> {{ Str::plural('day', $summary['days_reported']) }} reported
            </x-dashboard.highlight-card>

            <x-dashboard.stat-card label="Active Projects" :value="$summary['active_projects']" icon="folder" tone="navy" note="Projects you own or actively participate in" />
            <x-dashboard.stat-card label="Assigned Tasks" :value="$summary['assigned_tasks']" icon="list" tone="blue" note="Your active, non-cancelled assignments" />
            <x-dashboard.stat-card label="Completed" :value="$summary['completed_tasks']" icon="check" tone="blue" note="Assigned tasks marked completed" />
            <x-dashboard.stat-card label="In Progress" :value="$summary['in_progress_tasks']" icon="progress" tone="gold" note="Assigned tasks currently underway" />
            <x-dashboard.stat-card label="Overdue" :value="$summary['overdue_tasks']" icon="alert" :tone="$summary['overdue_tasks'] > 0 ? 'red' : 'navy'" :note="$summary['overdue_tasks'] > 0 ? 'Incomplete assignments past their due date' : 'Nothing past its due date'" />
        </div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="active-projects-heading">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 id="active-projects-heading" class="text-xl font-bold text-slate-900">Active Projects</h2>
                <p class="mt-1 text-sm text-slate-600">Projects you own or actively participate in.</p>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($activeProjects as $project)
                    @php
                        $membership = $project->projectMembers->first();
                        $relationship = $project->owner_id === auth()->id()
                            ? 'Owner'
                            : ($membership?->project_role ? App\Models\Project::label($membership->project_role) : 'Project Member');
                    @endphp
                    <article class="p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-mono text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $project->project_code }}</p>
                                <h3 class="mt-1 text-base font-bold text-slate-900">{{ $project->title }}</h3>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-800">{{ App\Models\Project::label($project->status) }}</span>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $relationship }}</span>
                                </div>
                            </div>
                            @if ($project->dashboard_can_view)
                                <a href="{{ route('projects.show', $project) }}" class="shrink-0 text-sm font-semibold text-emerald-700 hover:text-emerald-900">View Project</a>
                            @endif
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                            <div><dt class="text-xs font-semibold uppercase text-slate-500">Start</dt><dd class="mt-1 font-semibold text-slate-700">{{ $project->start_date->format('d M Y') }}</dd></div>
                            <div><dt class="text-xs font-semibold uppercase text-slate-500">Deadline</dt><dd class="mt-1 font-semibold text-slate-700">{{ $project->due_date?->format('d M Y') ?? '—' }}</dd></div>
                            <div><dt class="text-xs font-semibold uppercase text-slate-500">Your Tasks</dt><dd class="mt-1 font-semibold text-slate-700">{{ $project->user_active_tasks_count }}</dd></div>
                        </dl>

                        <div class="mt-4">
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-600"><span>Progress</span><span>{{ number_format((float) $project->progress_percentage, 1) }}%</span></div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-emerald-600" style="width: {{ min(100, (float) $project->progress_percentage) }}%"></div></div>
                        </div>
                    </article>
                @empty
                    <div class="px-6 py-12 text-center">
                        <p class="font-semibold text-slate-700">No active projects</p>
                        <p class="mt-1 text-sm text-slate-500">Projects you own or actively join will appear here.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="upcoming-deadlines-heading">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 id="upcoming-deadlines-heading" class="text-xl font-bold text-slate-900">Upcoming Deadlines</h2>
                <p class="mt-1 text-sm text-slate-600">Your nearest assigned-task deadlines.</p>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($upcomingDeadlines as $task)
                    @php $daysRemaining = (int) today()->diffInDays($task->due_date); @endphp
                    <article class="p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="font-mono text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $task->task_code }}</p>
                                <h3 class="mt-1 font-bold text-slate-900">{{ $task->title }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ $task->project->project_code }} — {{ $task->project->title }}</p>
                            </div>
                            @if ($task->dashboard_can_view)
                                <a href="{{ route('tasks.show', $task) }}" class="shrink-0 text-sm font-semibold text-emerald-700 hover:text-emerald-900">View Task</a>
                            @endif
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs font-semibold">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ App\Models\Task::label($task->status) }}</span>
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-800">{{ $task->due_date->format('d M Y') }}</span>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-800">
                                @if ($daysRemaining === 0)
                                    Due today
                                @elseif ($daysRemaining === 1)
                                    Due tomorrow
                                @else
                                    {{ $daysRemaining }} days remaining
                                @endif
                            </span>
                        </div>
                    </article>
                @empty
                    <div class="px-6 py-12 text-center">
                        <p class="font-semibold text-slate-700">No upcoming deadlines in the next {{ $upcomingDeadlineDays }} days.</p>
                        <p class="mt-1 text-sm text-slate-500">Assigned tasks due in this period will appear here.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="mt-6" aria-labelledby="dashboard-charts-heading">
        <div class="mb-4">
            <h2 id="dashboard-charts-heading" class="text-xl font-bold text-slate-900">Your performance charts</h2>
            <p class="mt-1 text-sm text-slate-600">Visual summaries based only on your assignments and recorded work.</p>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h3 class="text-lg font-bold text-slate-900">Task Status</h3><p class="mt-1 text-sm text-slate-500">Your active assigned tasks by current status.</p></div>
                    @if ($chartData['task_status']['overdue'] > 0)
                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">{{ $chartData['task_status']['overdue'] }} overdue</span>
                    @endif
                </div>
                @if ($chartData['task_status']['total'] > 0)
                    <div class="mt-5 h-72"><div id="task-status-chart" class="h-full" aria-label="Task status chart" role="img"></div></div>
                @else
                    <div class="mt-5 flex h-72 items-center justify-center rounded-xl bg-slate-50 px-6 text-center text-sm text-slate-500">No assigned task data available.</div>
                @endif
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div><h3 class="text-lg font-bold text-slate-900">Hours by Project</h3><p class="mt-1 text-sm text-slate-500">Your recorded hours by project this calendar month.</p></div>
                @if (count($chartData['hours_by_project']['values']) > 0)
                    <div class="mt-5 h-72"><div id="hours-by-project-chart" class="h-full" aria-label="Hours by project chart" role="img"></div></div>
                @else
                    <div class="mt-5 flex h-72 items-center justify-center rounded-xl bg-slate-50 px-6 text-center text-sm text-slate-500">No work hours recorded this month.</div>
                @endif
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-2">
                <div><h3 class="text-lg font-bold text-slate-900">Weekly Hours</h3><p class="mt-1 text-sm text-slate-500">Your recorded hours from Monday through Sunday this week.</p></div>
                <div class="mt-5 h-72 sm:h-80"><div id="weekly-hours-chart" class="h-full" aria-label="Weekly hours chart" role="img"></div></div>
            </article>
        </div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2" aria-labelledby="recent-activity-heading">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 id="recent-activity-heading" class="text-xl font-bold text-slate-900">Recent Activity</h2>
                <p class="mt-1 text-sm text-slate-600">The latest recorded events connected to your work.</p>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($recentActivity as $activity)
                    <article class="flex gap-4 p-5 sm:px-6">
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-600"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h3 class="font-bold text-slate-900">{{ $activity['title'] }}</h3>
                                    <p class="mt-1 text-sm text-slate-600">{{ $activity['description'] }}</p>
                                </div>
                                @if ($activity['url'])
                                    <a href="{{ $activity['url'] }}" class="shrink-0 text-sm font-semibold text-emerald-700 hover:text-emerald-900">View</a>
                                @endif
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                                @if ($activity['code'])<span class="font-mono font-semibold text-slate-700">{{ $activity['code'] }}</span>@endif
                                <time datetime="{{ $activity['occurred_at']->toIso8601String() }}">{{ $activity['occurred_at']->format('d M Y, h:i A') }}</time>
                                @if ($activity['actor'])<span>by {{ $activity['actor'] }}</span>@endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="px-6 py-12 text-center text-sm text-slate-500">No recent activity has been recorded.</div>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="alerts-heading">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 id="alerts-heading" class="text-xl font-bold text-slate-900">Alerts</h2>
                <p class="mt-1 text-sm text-slate-600">Personal work items needing attention.</p>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($alerts as $alert)
                    <article @class([
                        'border-l-4 p-5',
                        'border-rose-600 bg-rose-50/40' => $alert['severity'] === 'danger',
                        'border-amber-500 bg-amber-50/40' => $alert['severity'] === 'warning',
                        'border-sky-600 bg-sky-50/40' => $alert['severity'] === 'info',
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 @class([
                                    'text-sm font-bold',
                                    'text-rose-800' => $alert['severity'] === 'danger',
                                    'text-amber-800' => $alert['severity'] === 'warning',
                                    'text-sky-800' => $alert['severity'] === 'info',
                                ])>{{ $alert['title'] }}</h3>
                                <p class="mt-1 text-sm leading-5 text-slate-700">{{ $alert['message'] }}</p>
                            </div>
                            @if ($alert['url'])
                                <a href="{{ $alert['url'] }}" class="shrink-0 text-xs font-semibold text-emerald-700 hover:text-emerald-900">View</a>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="px-6 py-12 text-center text-sm text-slate-500">No alerts require your attention.</div>
                @endforelse
            </div>
        </section>
    </div>

    <script id="dashboard-chart-data" type="application/json">@json($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
@endsection

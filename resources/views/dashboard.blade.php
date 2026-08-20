@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-l-4 border-emerald-700 p-6 sm:p-8 lg:p-10">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Centralized Library Staff Performance System</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900">Welcome, {{ auth()->user()->name }}</h2>
            <p class="mt-3 text-slate-600">
                You are signed in as <strong class="font-semibold text-slate-800">{{ auth()->user()->getRoleNames()->join(', ') ?: 'User' }}</strong>.
            </p>
        </div>
    </section>

    @php
        $cards = [
            ['label' => 'Hours Worked This Month', 'value' => $summary['hours_this_month'], 'note' => 'Recorded time this calendar month', 'accent' => 'emerald'],
            ['label' => 'Active Projects', 'value' => $summary['active_projects'], 'note' => 'Projects you own or actively participate in', 'accent' => 'navy'],
            ['label' => 'Assigned Tasks', 'value' => $summary['assigned_tasks'], 'note' => 'Your active, non-cancelled assignments', 'accent' => 'navy'],
            ['label' => 'Completed Tasks', 'value' => $summary['completed_tasks'], 'note' => 'Your assigned tasks marked completed', 'accent' => 'emerald'],
            ['label' => 'In Progress', 'value' => $summary['in_progress_tasks'], 'note' => 'Your assigned tasks currently underway', 'accent' => 'emerald'],
            ['label' => 'Overdue', 'value' => $summary['overdue_tasks'], 'note' => 'Incomplete assignments past their due date', 'accent' => 'rose'],
            ['label' => 'Days Reported This Month', 'value' => $summary['days_reported'], 'note' => 'Distinct work dates recorded this month', 'accent' => 'navy'],
            ['label' => 'Completion Rate', 'value' => number_format($summary['completion_rate'], 1).'%', 'note' => 'Completed tasks as a share of your assignments', 'accent' => 'emerald'],
        ];
    @endphp

    <section class="mt-6" aria-labelledby="personal-summary-heading">
        <div class="mb-4">
            <h2 id="personal-summary-heading" class="text-xl font-bold text-slate-900">Your work summary</h2>
            <p class="mt-1 text-sm text-slate-600">A personal overview based only on your projects, assignments, and work entries.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <article class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <span @class([
                        'absolute inset-x-0 top-0 h-1',
                        'bg-emerald-600' => $card['accent'] === 'emerald',
                        'bg-slate-800' => $card['accent'] === 'navy',
                        'bg-rose-600' => $card['accent'] === 'rose',
                    ])></span>
                    <p class="text-sm font-semibold text-slate-600">{{ $card['label'] }}</p>
                    <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ $card['value'] }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ $card['note'] }}</p>
                </article>
            @endforeach
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
                    <div class="mt-5 h-72"><canvas id="task-status-chart" aria-label="Task status chart" role="img"></canvas></div>
                @else
                    <div class="mt-5 flex h-72 items-center justify-center rounded-xl bg-slate-50 px-6 text-center text-sm text-slate-500">No assigned task data available.</div>
                @endif
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div><h3 class="text-lg font-bold text-slate-900">Hours by Project</h3><p class="mt-1 text-sm text-slate-500">Your recorded hours by project this calendar month.</p></div>
                @if (count($chartData['hours_by_project']['values']) > 0)
                    <div class="mt-5 h-72"><canvas id="hours-by-project-chart" aria-label="Hours by project chart" role="img"></canvas></div>
                @else
                    <div class="mt-5 flex h-72 items-center justify-center rounded-xl bg-slate-50 px-6 text-center text-sm text-slate-500">No work hours recorded this month.</div>
                @endif
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-2">
                <div><h3 class="text-lg font-bold text-slate-900">Weekly Hours</h3><p class="mt-1 text-sm text-slate-500">Your recorded hours from Monday through Sunday this week.</p></div>
                <div class="mt-5 h-72 sm:h-80"><canvas id="weekly-hours-chart" aria-label="Weekly hours chart" role="img"></canvas></div>
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

@extends('layouts.app')
@section('title', 'Campus Dashboard')
@section('page-title', 'Campus Dashboard')
@section('content')
<div class="space-y-6">
    @php
        $pendingReports = $campus ? $summary['reports']->get('pending_review', 0) : 0;
    @endphp

    <x-dashboard.hero
        :eyebrow="'Campus performance · '.$period->format('F Y')"
        :title="($campus?->name ?? 'Campus not assigned').' Dashboard'"
        :description="'Management visibility for '.$period->format('F Y').'. Task figures use current status for assignments made in this period.'"
    >
        @if($campus && $campus->is_active)
            <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/20">{{ $summary['staff_reporting'] }} of {{ $summary['total_staff'] }} staff reporting</span>
            <span @class([
                'rounded-full px-3 py-1 ring-1',
                'bg-busitema-gold text-busitema-navy ring-busitema-gold' => $pendingReports > 0,
                'bg-white/10 ring-white/20' => $pendingReports === 0,
            ])>{{ $pendingReports }} {{ Str::plural('report', $pendingReports) }} pending review</span>
            @if($summary['overdue_tasks'] > 0)
                <span class="rounded-full bg-red-600 px-3 py-1 text-white ring-1 ring-red-500">{{ $summary['overdue_tasks'] }} overdue {{ Str::plural('task', $summary['overdue_tasks']) }}</span>
            @endif
        @endif
        <x-slot:actions>
            <x-dashboard.period-filter :period="$period" />
        </x-slot:actions>
    </x-dashboard.hero>

    @if(!$campus || !$campus->is_active)
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-900">Your account does not have a valid active campus assignment. Contact an administrator to correct your staff profile.</section>
    @else
        @php
            [$hoursValue, $hoursUnit] = array_pad(explode(' ', App\Models\WorkEntry::formatMinutes($summary['minutes']), 2), 2, '');
            $completionRate = $summary['active_tasks'] > 0 ? $summary['completed_tasks'] / $summary['active_tasks'] * 100 : 0;
            $activeStaffShare = $summary['total_staff'] > 0 ? round($summary['active_staff'] / $summary['total_staff'] * 100) : 0;
        @endphp

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Campus summary">
            <x-dashboard.gauge-card chart-id="campus-completion-chart" :rate="$completionRate" class="sm:col-span-2 lg:col-span-1 lg:row-span-2">
                <span class="font-bold text-heading">{{ $summary['completed_tasks'] }}</span> of
                <span class="font-bold text-heading">{{ $summary['active_tasks'] }}</span> campus {{ Str::plural('task', $summary['active_tasks']) }} completed
            </x-dashboard.gauge-card>

            <x-dashboard.highlight-card label="Hours Recorded" :value="$hoursValue" :unit="$hoursUnit" note="Campus staff time this period">
                <strong class="font-bold">{{ $summary['staff_reporting'] }}</strong> {{ Str::plural('staff member', $summary['staff_reporting']) }} reporting
            </x-dashboard.highlight-card>

            <x-dashboard.stat-card label="Total Staff" :value="$summary['total_staff']" icon="users" tone="blue" note="Assigned to this campus">
                <div class="flex items-center justify-between text-xs font-semibold">
                    <span class="text-busitema-blue">{{ $summary['active_staff'] }} active</span>
                    <span class="text-slate-500">{{ $activeStaffShare }}%</span>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-busitema-blue" style="width: {{ $activeStaffShare }}%"></div></div>
            </x-dashboard.stat-card>
            <x-dashboard.stat-card label="Active Projects" :value="$summary['active_projects']" icon="folder" tone="navy" note="Projects running on this campus" />
            <x-dashboard.stat-card label="Completed Tasks" :value="$summary['completed_tasks']" icon="check" tone="blue" note="Assignments marked completed" />
            <x-dashboard.stat-card label="Tasks In Progress" :value="$summary['in_progress_tasks']" icon="progress" tone="gold" note="Assignments currently underway" />
            <x-dashboard.stat-card label="Overdue Tasks" :value="$summary['overdue_tasks']" icon="alert" :tone="$summary['overdue_tasks'] > 0 ? 'red' : 'navy'" :note="$summary['overdue_tasks'] > 0 ? 'Incomplete and past their due date' : 'Nothing past its due date'" />
        </section>

        <section class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
            <x-dashboard.panel title="Staff Task Status" subtitle="Campus assignments by current status.">
                <div id="campus-task-status-chart" class="h-72" role="img" aria-label="Staff task status chart"></div>
            </x-dashboard.panel>
            <x-dashboard.panel title="Hours by Staff" subtitle="Recorded hours per staff member.">
                <div id="campus-hours-staff-chart" class="h-72" role="img" aria-label="Hours by staff chart"></div>
            </x-dashboard.panel>
            <x-dashboard.panel title="Hours by Project" subtitle="Where campus time was spent." class="lg:col-span-2 xl:col-span-1">
                <div id="campus-hours-project-chart" class="h-72" role="img" aria-label="Hours by project chart"></div>
            </x-dashboard.panel>
        </section>
        <script type="application/json" id="campus-dashboard-chart-data">{!! json_encode($charts, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>

        <x-campus-report-table title="Staff Performance Overview" subtitle="No ranking or inferred score is applied." :headers="['Staff Member','Position / Library','Hours','Days','Assigned','Completed','In Progress','Overdue','Completion','Report','']">
            @forelse($staffRows as $row)
                <tr>
                    <td class="px-4 py-3"><div class="flex items-center gap-3"><x-user-avatar :user="$row['user']" class="h-9! w-9! text-xs!" /><span class="font-semibold text-heading">{{ $row['user']->name }}</span></div></td>
                    <td class="px-4 py-3 text-slate-600">{{ $row['user']->staffProfile?->position?->name ?? '—' }}<br><span class="text-xs text-slate-500">{{ $row['user']->staffProfile?->library?->name ?? '—' }}</span></td>
                    <td class="whitespace-nowrap px-4 py-3 font-semibold">{{ App\Models\WorkEntry::formatMinutes($row['minutes']) }}</td>
                    <td class="px-4 py-3">{{ $row['days'] }}</td>
                    <td class="px-4 py-3">{{ $row['assigned'] }}</td>
                    <td class="px-4 py-3">{{ $row['completed'] }}</td>
                    <td class="px-4 py-3">{{ $row['in_progress'] }}</td>
                    <td @class(['px-4 py-3', 'font-bold text-red-600' => $row['overdue'] > 0])>{{ $row['overdue'] }}</td>
                    <td class="px-4 py-3"><x-dashboard.meter :value="$row['completion_rate']" /></td>
                    <td class="px-4 py-3"><x-status-badge :status="$row['report_status'] ? App\Models\MonthlyReport::label($row['report_status']) : 'No Report'" class="whitespace-nowrap" /></td>
                    <td class="whitespace-nowrap px-4 py-3"><a class="font-semibold text-busitema-blue hover:underline" href="{{ route('performance.staff.show', ['staff'=>$row['user'],'month'=>$period->month,'year'=>$period->year]) }}">View Performance</a></td>
                </tr>
            @empty
                <tr><td colspan="11" class="px-5 py-10 text-center text-slate-500">No staff are assigned to this campus.</td></tr>
            @endforelse
        </x-campus-report-table>

        <x-campus-report-table title="Campus Projects" subtitle="Active projects with campus participation." :headers="['Project','Status','Progress','Dates','Campus Staff','Tasks','Completed','In Progress','Overdue','']">
            @forelse($projects as $project)
                @php($pt = $project->tasks)
                <tr>
                    <td class="px-4 py-3"><span class="font-mono text-xs font-bold text-busitema-blue">{{ $project->project_code }}</span><br><span class="font-semibold text-heading">{{ $project->title }}</span></td>
                    <td class="px-4 py-3"><x-status-badge :status="App\Models\Project::label($project->status)" class="whitespace-nowrap" /></td>
                    <td class="px-4 py-3"><x-dashboard.meter :value="$project->progress_percentage" tone="gold" /></td>
                    <td class="whitespace-nowrap px-4 py-3 text-xs">{{ $project->start_date?->format('d M Y') ?? '—' }}<br><span class="text-slate-500">to {{ $project->due_date?->format('d M Y') ?? '—' }}</span></td>
                    <td class="px-4 py-3">{{ $pt->flatMap->taskAssignees->pluck('user_id')->unique()->count() }}</td>
                    <td class="px-4 py-3">{{ $pt->count() }}</td>
                    <td class="px-4 py-3">{{ $pt->where('status','completed')->count() }}</td>
                    <td class="px-4 py-3">{{ $pt->where('status','in_progress')->count() }}</td>
                    @php($projectOverdue = $pt->where('is_overdue',true)->count())
                    <td @class(['px-4 py-3', 'font-bold text-red-600' => $projectOverdue > 0])>{{ $projectOverdue }}</td>
                    <td class="px-4 py-3">@can('view',$project)<a href="{{ route('projects.show',$project) }}" class="font-semibold text-busitema-blue hover:underline">View</a>@endcan</td>
                </tr>
            @empty
                <tr><td colspan="10" class="px-5 py-10 text-center text-slate-500">No active campus projects.</td></tr>
            @endforelse
        </x-campus-report-table>

        <section class="grid gap-6 lg:grid-cols-2">
            @foreach([
                'in_progress' => ['Tasks In Progress', 'border-busitema-blue'],
                'due_soon' => ['Tasks Due Soon', 'border-busitema-gold'],
                'overdue' => ['Overdue Tasks', 'border-red-600'],
                'pending_review' => ['Tasks Pending Review', 'border-ink'],
            ] as $key => [$heading, $accent])
                <x-dashboard.panel :title="$heading">
                    <x-slot:aside><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-heading">{{ count($taskSections[$key]) }}</span></x-slot:aside>
                    <div class="space-y-3">
                        @forelse($taskSections[$key] as $task)
                            <div class="rounded-xl border border-l-4 border-slate-200 {{ $accent }} p-4">
                                <div class="flex justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-mono text-xs font-bold text-busitema-blue">{{ $task->task_code }}</p>
                                        <p class="font-semibold text-heading">{{ $task->title }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $task->project?->title }} · {{ $task->assignees->pluck('name')->join(', ') ?: 'Unassigned' }}</p>
                                    </div>
                                    <span class="text-sm font-bold text-heading">{{ number_format($task->progress_percentage,1) }}%</span>
                                </div>
                                <div class="mt-2 flex justify-between text-xs text-slate-500">
                                    <span>{{ App\Models\Task::label($task->status) }} · Due {{ $task->due_date?->format('d M Y') ?? '—' }}</span>
                                    @can('view',$task)<a href="{{ route('tasks.show',$task) }}" class="font-semibold text-busitema-blue hover:underline">View</a>@endcan
                                </div>
                            </div>
                        @empty
                            <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No matching tasks.</p>
                        @endforelse
                    </div>
                </x-dashboard.panel>
            @endforeach
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <x-dashboard.panel title="Monthly Report Status" subtitle="Staff monthly reports for this period.">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach([
                        ['Draft', $summary['reports']->get('draft', 0), 'bg-slate-400'],
                        ['Pending Review', $summary['reports']->get('pending_review', 0), 'bg-busitema-gold'],
                        ['Returned', $summary['reports']->get('returned_for_correction', 0), 'bg-red-600'],
                        ['Approved', $summary['reports']->get('approved', 0), 'bg-busitema-blue'],
                        ['No Report', $summary['no_report'], 'bg-ink'],
                    ] as [$label, $count, $dot])
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="flex items-center gap-2 text-sm text-slate-500"><span class="h-2 w-2 rounded-full {{ $dot }}"></span>{{ $label }}</p>
                            <p class="mt-1 text-2xl font-extrabold text-heading">{{ $count }}</p>
                        </div>
                    @endforeach
                </div>
            </x-dashboard.panel>

            <x-dashboard.panel title="Recent Campus Activity" subtitle="Latest recorded events on this campus.">
                <ol class="relative space-y-5 border-l-2 border-slate-200 pl-5">
                    @forelse($recentActivity as $activity)
                        <li class="relative">
                            <span class="absolute -left-6.75 top-1.5 h-3 w-3 rounded-full border-2 border-white bg-busitema-gold ring-2 ring-busitema-gold/30"></span>
                            <p class="font-semibold text-heading">{{ $activity['title'] }}</p>
                            <p class="text-sm text-slate-600">{{ $activity['description'] }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $activity['user'] }} · {{ $activity['at']?->diffForHumans() }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">No recent campus activity.</li>
                    @endforelse
                </ol>
            </x-dashboard.panel>
        </section>
    @endif
</div>
@endsection

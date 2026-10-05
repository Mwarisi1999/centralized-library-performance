@extends('layouts.app')
@section('title', 'University Librarian Dashboard')
@section('page-title', 'University Librarian Dashboard')
@section('content')
@php
    [$hoursValue, $hoursUnit] = array_pad(explode(' ', App\Models\WorkEntry::formatMinutes($summary['minutes']), 2), 2, '');
    $completionRate = $summary['active_tasks'] > 0 ? $summary['completed_tasks'] / $summary['active_tasks'] * 100 : 0;
    $activeStaffShare = $summary['total_staff'] > 0 ? round($summary['active_staff'] / $summary['total_staff'] * 100) : 0;
    $totalCampusReports = $summary['reports_finalized'] + $summary['reports_outstanding'];
    $finalizedShare = $totalCampusReports > 0 ? round($summary['reports_finalized'] / $totalCampusReports * 100) : 0;
@endphp
<div class="space-y-6">
    <x-dashboard.hero
        :eyebrow="'Institution-wide oversight · '.$period->format('F Y')"
        title="University Librarian Dashboard"
        :description="'Read-only library performance visibility for '.$period->format('F Y').'.'"
    >
        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/20">{{ $summary['reports_finalized'] }} of {{ $totalCampusReports }} campus reports finalized</span>
        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/20">{{ $summary['in_progress_tasks'] }} {{ Str::plural('task', $summary['in_progress_tasks']) }} in progress</span>
        @if($summary['overdue_tasks'] > 0)
            <span class="rounded-full bg-red-600 px-3 py-1 text-white ring-1 ring-red-500">{{ $summary['overdue_tasks'] }} overdue {{ Str::plural('task', $summary['overdue_tasks']) }}</span>
        @endif
        <x-slot:actions>
            <div class="flex flex-col gap-2 sm:items-end">
                <x-dashboard.period-filter :period="$period" />
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('monthly-reports.reviews.index') }}" class="rounded-xl bg-busitema-gold px-4 py-2.5 text-sm font-bold text-busitema-navy shadow-sm transition hover:bg-busitema-yellow">Individual Report Oversight</a>
                    <a href="{{ route('university-dashboard.csv', ['month'=>$period->month,'year'=>$period->year]) }}" class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/25 transition hover:bg-white/20">Export Institution CSV</a>
                </div>
            </div>
        </x-slot:actions>
    </x-dashboard.hero>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Institution summary">
        <x-dashboard.gauge-card label="Institution Completion" chart-id="university-completion-gauge" :rate="$completionRate" class="sm:col-span-2 lg:col-span-1 lg:row-span-2">
            <span class="font-bold text-heading">{{ $summary['completed_tasks'] }}</span> of
            <span class="font-bold text-heading">{{ $summary['active_tasks'] }}</span> {{ Str::plural('task', $summary['active_tasks']) }} completed
        </x-dashboard.gauge-card>

        <x-dashboard.highlight-card label="Total Hours Recorded" :value="$hoursValue" :unit="$hoursUnit" note="Across every campus this period">
            <strong class="font-bold">{{ $summary['staff_reporting'] }}</strong> {{ Str::plural('staff member', $summary['staff_reporting']) }} reporting
        </x-dashboard.highlight-card>

        <x-dashboard.stat-card label="Campuses" :value="$summary['total_campuses']" icon="campus" tone="navy" :note="$summary['total_libraries'].' '.Str::plural('library', $summary['total_libraries']).' across the university'" />
        <x-dashboard.stat-card label="Total Staff" :value="$summary['total_staff']" icon="users" tone="blue" note="Library staff institution-wide">
            <div class="flex items-center justify-between text-xs font-semibold">
                <span class="text-busitema-blue">{{ $summary['active_staff'] }} active</span>
                <span class="text-slate-500">{{ $activeStaffShare }}%</span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-busitema-blue" style="width: {{ $activeStaffShare }}%"></div></div>
        </x-dashboard.stat-card>
        <x-dashboard.stat-card label="Active Projects" :value="$summary['active_projects']" icon="folder" tone="navy" :note="$summary['active_tasks'].' active '.Str::plural('task', $summary['active_tasks'])" />
        <x-dashboard.stat-card label="Campus Reports" :value="$summary['reports_finalized'].' / '.$totalCampusReports" icon="report" :tone="$summary['reports_outstanding'] > 0 ? 'gold' : 'navy'" :note="$summary['reports_outstanding'].' outstanding'">
            <div class="h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-busitema-gold" style="width: {{ $finalizedShare }}%"></div></div>
        </x-dashboard.stat-card>
        <x-dashboard.stat-card label="Overdue Tasks" :value="$summary['overdue_tasks']" icon="alert" :tone="$summary['overdue_tasks'] > 0 ? 'red' : 'navy'" :note="$summary['overdue_tasks'] > 0 ? 'Incomplete and past their due date' : 'Nothing past its due date'" />
    </section>

    <section class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
        <x-dashboard.panel title="Hours by Campus" subtitle="Recorded hours per campus this period." class="lg:col-span-2">
            <div id="university-hours-campus-chart" class="h-72" role="img" aria-label="Hours by campus chart"></div>
        </x-dashboard.panel>
        <x-dashboard.panel title="Task Status Across the University" subtitle="All assignments by current status.">
            <div id="university-task-status-chart" class="h-72" role="img" aria-label="Task status chart"></div>
        </x-dashboard.panel>
        <x-dashboard.panel title="Campus Completion Rates" subtitle="Completed share of each campus's tasks.">
            <div id="university-completion-chart" class="h-72" role="img" aria-label="Campus completion rates chart"></div>
        </x-dashboard.panel>
        <x-dashboard.panel title="Campus Report Status" subtitle="Finalized campus reports this period.">
            <div id="university-report-chart" class="h-72" role="img" aria-label="Campus report status chart"></div>
        </x-dashboard.panel>
        <x-dashboard.panel title="Project Progress" subtitle="Stored progress of active projects." class="lg:col-span-2 xl:col-span-1">
            <div id="university-project-chart" class="h-72" role="img" aria-label="Project progress chart"></div>
        </x-dashboard.panel>
    </section>

    <x-campus-report-table title="Campus Performance Overview" subtitle="Side-by-side campus metrics for the selected period." :headers="['Campus','Libraries','Total Staff','Active Staff','Hours','Staff Reporting','Tasks Assigned','Completed','In Progress','Overdue','Completion Rate','Active Projects','Campus Report Status','Action']">
        @forelse($campusRows as $row)
            <tr>
                <td class="px-4 py-3 font-semibold text-heading">{{ $row['campus']->name }}</td>
                @foreach([$row['libraries'],$row['total_staff'],$row['active_staff'],App\Models\WorkEntry::formatMinutes($row['minutes']),$row['staff_reporting'],$row['assigned'],$row['completed'],$row['in_progress']] as $cell)
                    <td class="whitespace-nowrap px-4 py-3">{{ $cell }}</td>
                @endforeach
                <td @class(['px-4 py-3', 'font-bold text-red-600' => $row['overdue'] > 0])>{{ $row['overdue'] }}</td>
                <td class="px-4 py-3"><x-dashboard.meter :value="$row['completion_rate']" /></td>
                <td class="px-4 py-3">{{ $row['active_projects'] }}</td>
                <td class="px-4 py-3"><x-status-badge :status="$row['report'] ? 'Finalized' : 'Not Finalized'" class="whitespace-nowrap" /></td>
                <td class="whitespace-nowrap px-4 py-3"><a class="font-semibold text-busitema-blue hover:underline" href="{{ route('university-dashboard.campus',['campus'=>$row['campus'],'month'=>$period->month,'year'=>$period->year]) }}">View Details</a></td>
            </tr>
        @empty
            <tr><td colspan="14" class="p-8 text-center text-slate-500">No active campuses.</td></tr>
        @endforelse
    </x-campus-report-table>

    <x-campus-report-table title="Campus Reporting Status" subtitle="Who has finalized this period's campus report." :headers="['Campus','Campus Librarian','Report Status','Report Code','Finalized By','Finalized At','Action']">
        @foreach($reportRows as $row)
            <tr>
                <td class="px-4 py-3 font-semibold text-heading">{{ $row['campus']->name }}</td>
                <td class="px-4 py-3">{{ $row['librarian']?->name ?? 'Not assigned' }}</td>
                <td class="px-4 py-3"><x-status-badge :status="$row['report'] ? 'Finalized' : 'Not Finalized'" class="whitespace-nowrap" /></td>
                <td class="px-4 py-3 font-mono text-xs">{{ $row['report']?->report_code ?? '—' }}</td>
                <td class="px-4 py-3">{{ $row['report']?->finalizer?->name ?? '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3">{{ $row['report']?->finalized_at?->format('d M Y, H:i') ?? '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3">@if($row['report'])<a class="font-semibold text-busitema-blue hover:underline" href="{{ route('campus-reports.show',$row['report']) }}">Open Snapshot</a>@else<span class="text-slate-400">—</span>@endif</td>
            </tr>
        @endforeach
    </x-campus-report-table>

    <x-campus-report-table title="Staff Performance Overview" subtitle="Factual activity metrics; no ranking is applied." :headers="['Staff Member','Campus','Library','Position','Hours','Days Reported','Tasks Assigned','Completed','In Progress','Overdue','Completion Rate','Monthly Report Status','Action']">
        @foreach($staffRows as $row)
            <tr>
                <td class="px-4 py-3"><div class="flex items-center gap-3"><x-user-avatar :user="$row['user']" class="h-9! w-9! text-xs!" /><span class="whitespace-nowrap font-semibold text-heading">{{ $row['user']->name }}</span></div></td>
                @foreach([$row['campus']->name,$row['user']->staffProfile?->library?->name ?? '—',$row['user']->staffProfile?->position?->name ?? '—',App\Models\WorkEntry::formatMinutes($row['minutes']),$row['days'],$row['assigned'],$row['completed'],$row['in_progress']] as $cell)
                    <td class="whitespace-nowrap px-4 py-3">{{ $cell }}</td>
                @endforeach
                <td @class(['px-4 py-3', 'font-bold text-red-600' => $row['overdue'] > 0])>{{ $row['overdue'] }}</td>
                <td class="px-4 py-3"><x-dashboard.meter :value="$row['completion_rate']" /></td>
                <td class="px-4 py-3"><x-status-badge :status="$row['report_status'] ? App\Models\MonthlyReport::label($row['report_status']) : 'No Report'" class="whitespace-nowrap" /></td>
                <td class="whitespace-nowrap px-4 py-3"><a href="{{ route('performance.staff.show',['staff'=>$row['user'],'month'=>$period->month,'year'=>$period->year]) }}" class="font-semibold text-busitema-blue hover:underline">View Performance</a></td>
            </tr>
        @endforeach
    </x-campus-report-table>

    <x-campus-report-table title="Project Performance Overview" subtitle="Active projects across all campuses." :headers="['Project Code','Project','Status','Progress','Campuses','Staff','Tasks','Completed','In Progress','Overdue']">
        @foreach($projectRows as $row)
            <tr>
                <td class="px-4 py-3 font-mono text-xs font-bold text-busitema-blue">{{ $row['project']->project_code }}</td>
                <td class="px-4 py-3 font-semibold text-heading">{{ $row['project']->title }}</td>
                <td class="px-4 py-3"><x-status-badge :status="App\Models\Project::label($row['project']->status)" class="whitespace-nowrap" /></td>
                <td class="px-4 py-3"><x-dashboard.meter :value="$row['project']->progress_percentage" tone="gold" /></td>
                <td class="px-4 py-3">{{ ($row['project']->scope === 'university_wide' ? 'University-wide' : $row['project']->campuses->pluck('name')->join(', ')) ?: '—' }}</td>
                @foreach([$row['staff'],$row['tasks'],$row['completed'],$row['in_progress']] as $cell)
                    <td class="px-4 py-3">{{ $cell ?: '—' }}</td>
                @endforeach
                <td @class(['px-4 py-3', 'font-bold text-red-600' => $row['overdue'] > 0])>{{ $row['overdue'] ?: '—' }}</td>
            </tr>
        @endforeach
    </x-campus-report-table>

    <x-dashboard.panel title="Recent Institution Activity" subtitle="Latest recorded events across the university.">
        <ol class="relative space-y-5 border-l-2 border-slate-200 pl-5">
            @forelse($recentActivity as $activity)
                <li class="relative">
                    <span class="absolute -left-6.75 top-1.5 h-3 w-3 rounded-full border-2 border-white bg-busitema-gold ring-2 ring-busitema-gold/30"></span>
                    <p class="font-semibold text-heading">{{ $activity['title'] }}</p>
                    <p class="text-sm text-slate-600">{{ $activity['description'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $activity['user'] }} · {{ $activity['at']?->diffForHumans() }}</p>
                </li>
            @empty
                <li class="text-sm text-slate-500">No recent institution activity.</li>
            @endforelse
        </ol>
    </x-dashboard.panel>
    <script type="application/json" id="university-dashboard-chart-data">@json($charts)</script>
</div>
@endsection

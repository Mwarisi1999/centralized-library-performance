@extends('layouts.app')
@section('title', 'Campus Performance Detail')
@section('page-title', 'Campus Performance Detail')
@section('content')
@php
    [$hoursValue, $hoursUnit] = array_pad(explode(' ', App\Models\WorkEntry::formatMinutes($summary['minutes']), 2), 2, '');
    $completionRate = $summary['active_tasks'] > 0 ? $summary['completed_tasks'] / $summary['active_tasks'] * 100 : 0;
    $activeStaffShare = $summary['total_staff'] > 0 ? round($summary['active_staff'] / $summary['total_staff'] * 100) : 0;
@endphp
<div class="space-y-6">
    <a href="{{ route('university-dashboard.index',['month'=>$period->month,'year'=>$period->year]) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-busitema-blue hover:underline">&larr; Back to University Dashboard</a>

    <x-dashboard.hero :eyebrow="$period->format('F Y').' · Read-only oversight'" :title="$campus->name" :description="'Libraries: '.($libraries->pluck('name')->join(', ') ?: 'None assigned')">
        @if($report)
            <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/20">Campus report finalized</span>
        @else
            <span class="rounded-full bg-busitema-gold px-3 py-1 text-busitema-navy ring-1 ring-busitema-gold">Campus report: Not Finalized</span>
        @endif
        @if($report)
            <x-slot:actions>
                <a href="{{ route('campus-reports.show',$report) }}" class="rounded-xl bg-busitema-gold px-4 py-2.5 text-sm font-bold text-busitema-navy shadow-sm transition hover:bg-busitema-yellow">Open Finalized Report {{ $report->report_code }}</a>
            </x-slot:actions>
        @endif
    </x-dashboard.hero>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Campus summary">
        <x-dashboard.gauge-card chart-id="campus-detail-completion-chart" :rate="$completionRate" class="sm:col-span-2 lg:col-span-1 lg:row-span-2">
            <span class="font-bold text-heading">{{ $summary['completed_tasks'] }}</span> of
            <span class="font-bold text-heading">{{ $summary['active_tasks'] }}</span> {{ Str::plural('task', $summary['active_tasks']) }} completed
        </x-dashboard.gauge-card>
        <x-dashboard.highlight-card label="Hours" :value="$hoursValue" :unit="$hoursUnit" note="Campus staff time this period">
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
        <x-dashboard.stat-card label="Active Tasks" :value="$summary['active_tasks']" icon="list" tone="blue" note="Assignments in this period" />
        <x-dashboard.stat-card label="In Progress" :value="$summary['in_progress_tasks']" icon="progress" tone="gold" note="Assignments currently underway" />
        <x-dashboard.stat-card label="Overdue" :value="$summary['overdue_tasks']" icon="alert" :tone="$summary['overdue_tasks'] > 0 ? 'red' : 'navy'" :note="$summary['overdue_tasks'] > 0 ? 'Incomplete and past their due date' : 'Nothing past its due date'" />
    </section>

    <x-campus-report-table title="Staff Performance" :headers="['Staff','Position','Library','Hours','Days','Assigned','Completed','In Progress','Overdue','Completion','Report']">
        @foreach($staffRows as $row)
            <tr>
                <td class="px-4 py-3"><div class="flex items-center gap-3"><x-user-avatar :user="$row['user']" class="h-9! w-9! text-xs!" /><span class="whitespace-nowrap font-semibold text-heading">{{ $row['user']->name }}</span></div></td>
                @foreach([$row['user']->staffProfile?->position?->name ?? '—',$row['user']->staffProfile?->library?->name ?? '—',App\Models\WorkEntry::formatMinutes($row['minutes']),$row['days'],$row['assigned'],$row['completed'],$row['in_progress']] as $cell)
                    <td class="whitespace-nowrap px-4 py-3">{{ $cell }}</td>
                @endforeach
                <td @class(['px-4 py-3', 'font-bold text-red-600' => $row['overdue'] > 0])>{{ $row['overdue'] }}</td>
                <td class="px-4 py-3"><x-dashboard.meter :value="$row['completion_rate']" /></td>
                <td class="px-4 py-3"><x-status-badge :status="$row['report_status'] ? App\Models\MonthlyReport::label($row['report_status']) : 'No Report'" class="whitespace-nowrap" /></td>
            </tr>
        @endforeach
    </x-campus-report-table>

    <x-campus-report-table title="Project Performance" :headers="['Code','Project','Status','Stored Progress']">
        @foreach($projects as $project)
            <tr>
                <td class="px-4 py-3 font-mono text-xs font-bold text-busitema-blue">{{ $project->project_code }}</td>
                <td class="px-4 py-3 font-semibold text-heading">{{ $project->title }}</td>
                <td class="px-4 py-3"><x-status-badge :status="App\Models\Project::label($project->status)" class="whitespace-nowrap" /></td>
                <td class="px-4 py-3"><x-dashboard.meter :value="$project->progress_percentage" tone="gold" class="max-w-xs" /></td>
            </tr>
        @endforeach
    </x-campus-report-table>

    <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">This view does not provide work-entry, task, monthly-report, or campus-report mutation actions.</p>
</div>
@endsection

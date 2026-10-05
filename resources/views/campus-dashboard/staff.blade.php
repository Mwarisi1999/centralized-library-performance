@extends('layouts.app')
@section('title', 'Staff Performance')
@section('page-title', 'Staff Performance')
@section('content')
@php
    $performance = $foundation['performance'];
    $reportLabel = $foundation['report'] ? App\Models\MonthlyReport::label($foundation['status']) : 'No Report';
    [$hoursValue, $hoursUnit] = array_pad(explode(' ', (string) $performance['total_hours'], 2), 2, '');
@endphp
<div class="space-y-6">
    <a href="{{ route('campus-dashboard.index',['month'=>$period->month,'year'=>$period->year]) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-busitema-blue hover:underline">&larr; Back to Campus Dashboard</a>

    <x-dashboard.hero :eyebrow="$campus->name.' · '.$period->format('F Y')" :title="$staff->name" :description="($staff->staffProfile?->position?->name ?? 'Position not set').' · '.($staff->staffProfile?->library?->name ?? 'Library not set')">
        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/20">Report: {{ $reportLabel }}</span>
        <x-slot:actions>
            <x-user-avatar :user="$staff" class="h-16! w-16! text-lg! ring-2! ring-busitema-gold!" />
        </x-slot:actions>
    </x-dashboard.hero>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Staff summary">
        <x-dashboard.gauge-card chart-id="staff-completion-chart" :rate="$performance['completion_rate']" class="sm:col-span-2 lg:col-span-1 lg:row-span-2">
            <span class="font-bold text-heading">{{ $performance['tasks_completed'] }}</span> of
            <span class="font-bold text-heading">{{ $performance['tasks_assigned'] }}</span> assigned {{ Str::plural('task', $performance['tasks_assigned']) }} completed
        </x-dashboard.gauge-card>
        <x-dashboard.highlight-card label="Total Hours" :value="$hoursValue" :unit="$hoursUnit" note="Recorded time this period">
            <strong class="font-bold">{{ $performance['days_reported'] }}</strong> {{ Str::plural('day', $performance['days_reported']) }} reported
        </x-dashboard.highlight-card>
        <x-dashboard.stat-card label="Tasks Assigned" :value="$performance['tasks_assigned']" icon="list" tone="blue" note="Assignments in this period" />
        <x-dashboard.stat-card label="In Progress / Pending" :value="$performance['pending_tasks']" icon="progress" tone="gold" note="Still open or awaiting review" />
        <x-dashboard.stat-card label="Overdue" :value="$performance['overdue_tasks']" icon="alert" :tone="$performance['overdue_tasks'] > 0 ? 'red' : 'navy'" :note="$performance['overdue_tasks'] > 0 ? 'Past their due date' : 'Nothing past its due date'" />
        <x-dashboard.stat-card label="Report Status" :value="$reportLabel" icon="report" tone="navy" note="Monthly report for this period" class="sm:col-span-2 lg:col-span-2" />
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <x-dashboard.panel title="Recent Work Entries" subtitle="Latest recorded work in this period.">
            <div class="space-y-3">
                @forelse($recentEntries as $entry)
                    <div class="rounded-xl border border-l-4 border-slate-200 border-l-busitema-blue p-4">
                        <div class="flex justify-between gap-3"><p class="font-semibold text-heading">{{ $entry->work_date->format('d M Y') }}</p><span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-heading">{{ App\Models\WorkEntry::formatMinutes($entry->duration_minutes) }}</span></div>
                        <p class="mt-1 text-sm text-slate-600">{{ $entry->project?->title }} · {{ $entry->task?->title }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $entry->work_description }}</p>
                        @can('view',$entry)<a href="{{ route('work-entries.show',$entry) }}" class="mt-2 inline-block text-sm font-semibold text-busitema-blue hover:underline">View authorized detail</a>@endcan
                    </div>
                @empty
                    <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No work entries in this period.</p>
                @endforelse
            </div>
        </x-dashboard.panel>
        <x-dashboard.panel title="Assigned Tasks" subtitle="Active assignments and their progress.">
            <div class="space-y-3">
                @forelse($assignedTasks as $task)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><p class="font-mono text-xs font-bold text-busitema-blue">{{ $task->task_code }}</p><p class="font-semibold text-heading">{{ $task->title }}</p></div>
                            <x-status-badge :status="App\Models\Task::label($task->status)" class="shrink-0" />
                        </div>
                        <x-dashboard.meter :value="$task->progress_percentage" class="mt-3" />
                        @can('view',$task)<a href="{{ route('tasks.show',$task) }}" class="mt-2 inline-block text-sm font-semibold text-busitema-blue hover:underline">View task</a>@endcan
                    </div>
                @empty
                    <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No active assigned tasks.</p>
                @endforelse
            </div>
        </x-dashboard.panel>
    </section>

    <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">This management view is read-only. Work-entry editing and monthly-report review remain governed by their existing ownership and stored-reviewer policies.</p>
</div>
@endsection

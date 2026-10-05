@extends('layouts.app')
@section('title','Staff Performance')
@section('section-label','Performance Oversight')
@section('page-title','Individual Performance')
@section('content')
@php
    [$hoursValue, $hoursUnit] = array_pad(explode(' ', App\Models\WorkEntry::formatMinutes($metrics['minutes']), 2), 2, '');
@endphp
<div class="mx-auto max-w-screen-2xl space-y-6">
    <x-dashboard.hero
        :eyebrow="trim(($staff->staffProfile?->campus?->name ?? '').' · '.$period->format('F Y'), ' ·')"
        :title="$staff->name"
        :description="($staff->staffProfile?->position?->name ?? 'Position not set').' · Factual activity metrics only; no ranking or inferred quality score.'"
    >
        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/20">{{ $metrics['projects'] }} {{ Str::plural('project', $metrics['projects']) }} worked</span>
        @if($metrics['overdue'] > 0)
            <span class="rounded-full bg-red-600 px-3 py-1 text-white ring-1 ring-red-500">{{ $metrics['overdue'] }} overdue {{ Str::plural('task', $metrics['overdue']) }}</span>
        @endif
        <x-slot:actions>
            <x-dashboard.period-filter :period="$period" />
        </x-slot:actions>
    </x-dashboard.hero>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Performance summary">
        <x-dashboard.gauge-card chart-id="performance-completion-chart" :rate="$metrics['completion_rate']" class="sm:col-span-2 lg:col-span-1 lg:row-span-2">
            <span class="font-bold text-heading">{{ $metrics['completed'] }}</span> of
            <span class="font-bold text-heading">{{ $metrics['assigned'] }}</span> assigned {{ Str::plural('task', $metrics['assigned']) }} completed
        </x-dashboard.gauge-card>
        <x-dashboard.highlight-card label="Hours" :value="$hoursValue" :unit="$hoursUnit" note="Recorded time this period">
            <strong class="font-bold">{{ $metrics['days'] }}</strong> reporting {{ Str::plural('day', $metrics['days']) }}
        </x-dashboard.highlight-card>
        <x-dashboard.stat-card label="Assigned Tasks" :value="$metrics['assigned']" icon="list" tone="blue" note="Assignments in this period" />
        <x-dashboard.stat-card label="Completed" :value="$metrics['completed']" icon="check" tone="blue" note="Assignments marked completed" />
        <x-dashboard.stat-card label="Overdue" :value="$metrics['overdue']" icon="alert" :tone="$metrics['overdue'] > 0 ? 'red' : 'navy'" :note="$metrics['overdue'] > 0 ? 'Past their due date' : 'Nothing past its due date'" />
        <x-dashboard.stat-card label="Projects Worked" :value="$metrics['projects']" icon="folder" tone="navy" note="Projects with recorded time" />
        <x-dashboard.stat-card label="Reports on File" :value="count($reports)" icon="report" tone="gold" note="Submitted monthly reports" />
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <x-dashboard.panel title="Daily Hours Trend" subtitle="Hours recorded each day of the period." class="lg:col-span-2">
            <div id="performance-daily-chart" class="h-72" role="img" aria-label="Daily hours trend chart"></div>
        </x-dashboard.panel>
        <x-dashboard.panel title="Task Status Distribution" subtitle="Assignments by current status.">
            <div id="performance-task-chart" class="h-72" role="img" aria-label="Task status distribution chart"></div>
        </x-dashboard.panel>
        <x-dashboard.panel title="Hours by Project" subtitle="Where time was spent.">
            <div id="performance-project-chart" class="h-72" role="img" aria-label="Hours by project chart"></div>
        </x-dashboard.panel>
        <x-dashboard.panel title="Work Location Distribution" subtitle="Work entries by location.">
            <div id="performance-location-chart" class="h-72" role="img" aria-label="Work location distribution chart"></div>
        </x-dashboard.panel>
        <x-dashboard.panel title="Monthly Report History" subtitle="Submitted monthly reports.">
            <div class="divide-y divide-slate-100">
                @forelse($reports as $report)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div>
                            <p class="font-mono text-xs font-bold text-busitema-blue">{{ $report->report_code }}</p>
                            <p class="text-sm text-heading">{{ now()->setYear($report->reporting_year)->setMonth($report->reporting_month)->format('F Y') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-status-badge :status="App\Models\MonthlyReport::label($report->status)" class="whitespace-nowrap" />
                            @can('view',$report)<a href="{{ route('monthly-reports.reviews.show',$report) }}" class="text-sm font-semibold text-busitema-blue hover:underline">Open report</a>@endcan
                        </div>
                    </div>
                @empty
                    <p class="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No submitted monthly reports.</p>
                @endforelse
            </div>
        </x-dashboard.panel>
    </section>

    <x-dashboard.panel title="Recent Period Activity" subtitle="Latest work entries in this period.">
        <div class="grid gap-3 md:grid-cols-2">
            @forelse($entries as $entry)
                <div class="rounded-xl border border-l-4 border-slate-200 border-l-busitema-blue p-4">
                    <div class="flex justify-between gap-3"><p class="font-semibold text-heading">{{ $entry->work_date->format('d M Y') }}</p><span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-heading">{{ App\Models\WorkEntry::formatMinutes($entry->duration_minutes) }}</span></div>
                    <p class="mt-1 text-sm text-slate-500">{{ $entry->project?->title }} · {{ $entry->work_location ?: 'Location not specified' }}</p>
                </div>
            @empty
                <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 md:col-span-2">No work entries in this period.</p>
            @endforelse
        </div>
    </x-dashboard.panel>

    <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">This oversight view is read-only and provides no route for editing another employee’s work entries or submitted snapshots.</p>
    <script type="application/json" id="performance-chart-data">@json($charts)</script>
</div>
@endsection

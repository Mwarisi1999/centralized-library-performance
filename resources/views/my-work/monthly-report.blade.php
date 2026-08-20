@extends('layouts.app')

@section('title', 'Individual Monthly Report')
@section('section-label', 'My Work')
@section('page-title', 'Monthly Report')

@section('content')
    <div class="mx-auto max-w-screen-2xl">
        <header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Individual Monthly Report</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ $period['label'] }}</h2>
                <p class="mt-2 text-slate-600">Personal monthly performance report for {{ $staff['name'] }}.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span @class([
                    'rounded-full px-3 py-1.5 text-sm font-semibold',
                    'bg-amber-50 text-amber-800' => $status === App\Models\MonthlyReport::STATUS_DRAFT,
                    'bg-blue-50 text-blue-800' => $status === App\Models\MonthlyReport::STATUS_PENDING_REVIEW,
                    'bg-red-50 text-red-800' => $status === App\Models\MonthlyReport::STATUS_RETURNED_FOR_CORRECTION,
                    'bg-emerald-50 text-emerald-800' => $status === App\Models\MonthlyReport::STATUS_APPROVED,
                ])>{{ App\Models\MonthlyReport::label($status) }}</span>
                @if ($report)<span class="font-mono text-xs font-bold text-slate-600">{{ $report->report_code }}</span>@endif
                <a target="_blank" href="{{ route('my-work.monthly-report.print', ['month' => $period['month'], 'year' => $period['year']]) }}" class="rounded-xl border border-emerald-700 px-4 py-2.5 text-sm font-semibold text-emerald-800">Print Report</a>
                <a href="{{ route('my-work.monthly-report.pdf', ['month' => $period['month'], 'year' => $period['year']]) }}" class="rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white">Download PDF</a>
                <a href="{{ route('my-work.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Back to My Work</a>
            </div>
        </header>

        @if ($errors->has('report'))
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">{{ $errors->first('report') }}</div>
        @endif

        @if ($report?->status === App\Models\MonthlyReport::STATUS_PENDING_REVIEW)
            <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-5 text-blue-900">
                <p class="font-bold">Submitted for Review</p>
                <p class="mt-1 text-sm">Awaiting review by {{ $report->reviewer?->name ?? 'your recorded supervisor' }}. Submitted {{ $report->submitted_at?->format('d F Y, h:i A') }}.</p>
            </div>
        @elseif ($report?->status === App\Models\MonthlyReport::STATUS_APPROVED)
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
                <p class="font-bold">Approved</p>
                <p class="mt-1 text-sm">Approved by {{ $report->reviewer?->name ?? 'your supervisor' }} on {{ $report->approved_at?->format('d F Y, h:i A') }}.</p>
                @if ($report->approval_remark)<p class="mt-3 whitespace-pre-line text-sm"><span class="font-semibold">Supervisor remark:</span> {{ $report->approval_remark }}</p>@endif
            </div>
        @elseif ($report?->status === App\Models\MonthlyReport::STATUS_RETURNED_FOR_CORRECTION)
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-5 text-red-900">
                <p class="font-bold">Returned for Correction</p>
                <p class="mt-1 text-sm">Returned by {{ $report->reviewer?->name ?? 'your supervisor' }} on {{ $report->returned_at?->format('d F Y, h:i A') }}.</p>
                <p class="mt-3 whitespace-pre-line text-sm"><span class="font-semibold">Required correction:</span> {{ $report->correction_reason }}</p>
            </div>
        @endif

        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <form method="GET" action="{{ route('my-work.monthly-report') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <label class="flex-1 text-sm font-semibold text-slate-700">Month
                    <select name="month" class="mt-2 w-full rounded-xl border-slate-300">
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}" @selected($period['month'] === $month)>{{ Carbon\CarbonImmutable::create(2000, $month, 1)->format('F') }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex-1 text-sm font-semibold text-slate-700">Year
                    <select name="year" class="mt-2 w-full rounded-xl border-slate-300">
                        @foreach (range(today()->year + 1, 2000) as $year)
                            <option value="{{ $year }}" @selected($period['year'] === $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="rounded-xl bg-slate-900 px-6 py-3 font-semibold text-white">View Period</button>
            </form>
        </section>

        <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6"><h3 class="text-lg font-bold text-slate-900">Staff Information</h3></div>
            <dl class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ([['Name', $staff['name']], ['Position', $staff['position']], ['Campus', $staff['campus']], ['Library', $staff['library']], ['Supervisor', $staff['supervisor']]] as [$label, $value])
                    <div class="bg-white p-5"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt><dd class="mt-2 font-semibold text-slate-800">{{ $value ?: '—' }}</dd></div>
                @endforeach
            </dl>
        </section>

        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div><h3 class="text-lg font-bold text-slate-900">Performance Summary</h3><p class="mt-1 text-sm text-slate-500">Calculated from your recorded work and active task assignments for this reporting period.</p></div>
            <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                @foreach ([
                    ['Total Hours', $performance['total_hours']],
                    ['Days Reported', number_format($performance['days_reported'])],
                    ['Tasks Assigned', number_format($performance['tasks_assigned'])],
                    ['Tasks Completed', number_format($performance['tasks_completed'])],
                    ['Pending Tasks', number_format($performance['pending_tasks'])],
                    ['Overdue Tasks', number_format($performance['overdue_tasks'])],
                    ['Completion Rate', number_format($performance['completion_rate'], 1).'%'],
                    ['Project Performance', number_format($performance['project_performance'], 1).'%'],
                ] as [$label, $value])
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-slate-700">{{ $value }}</p></div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div><h3 class="text-lg font-bold text-slate-900">Staff Narrative</h3><p class="mt-1 text-sm text-slate-500">Automatically summarized from your daily work entries for this reporting period.</p></div>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                @foreach ([
                    ['key_achievements', 'Key Achievements', 'No achievements/deliverables recorded for this period.'],
                    ['challenges', 'Challenges', 'No challenges recorded for this period.'],
                    ['corrective_actions', 'Corrective Actions', 'No corrective actions recorded for this period.'],
                    ['support_required', 'Support Required', 'No support requirements recorded for this period.'],
                    ['planned_activities_next_month', 'Planned Activities for Next Month', 'No planned follow-up activities recorded for this period.'],
                ] as [$field, $label, $emptyMessage])
                    <article @class(['rounded-xl border border-slate-200 bg-slate-50 p-5', 'md:col-span-2' => $loop->last])>
                        <h4 class="font-bold text-slate-900">{{ $label }}</h4>
                        @if ($narrative[$field])
                            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-700">
                                @foreach ($narrative[$field] as $item)
                                    <li class="whitespace-pre-line">{{ $item }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-3 text-sm text-slate-500">{{ $emptyMessage }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        @if (! $report || in_array($report->status, [App\Models\MonthlyReport::STATUS_DRAFT, App\Models\MonthlyReport::STATUS_RETURNED_FOR_CORRECTION], true))
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><h3 class="font-bold text-slate-900">{{ $report?->status === App\Models\MonthlyReport::STATUS_RETURNED_FOR_CORRECTION ? 'Resubmit Monthly Report' : 'Submit Monthly Report' }}</h3><p class="mt-1 text-sm text-slate-500">Submission freezes the displayed report for review by your recorded supervisor.</p></div>
                    <form method="POST" action="{{ route('my-work.monthly-report.submit') }}">@csrf<input type="hidden" name="month" value="{{ $period['month'] }}"><input type="hidden" name="year" value="{{ $period['year'] }}"><button class="rounded-xl bg-emerald-800 px-6 py-3 font-semibold text-white hover:bg-emerald-900">{{ $report?->status === App\Models\MonthlyReport::STATUS_RETURNED_FOR_CORRECTION ? 'Resubmit for Review' : 'Submit for Review' }}</button></form>
                </div>
            </section>
        @endif

        @if ($report?->activities->isNotEmpty())
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-lg font-bold text-slate-900">Report History</h3>
                <div class="mt-5 space-y-4">@foreach($report->activities->sortByDesc('created_at') as $activity)<article class="border-l-2 border-emerald-200 pl-4"><p class="font-semibold text-slate-900">{{ $activity->event_label }}</p><p class="mt-1 text-sm text-slate-600">{{ $activity->description }}</p>@if(data_get($activity->metadata, 'reason'))<p class="mt-1 whitespace-pre-line text-sm text-red-700">Reason: {{ data_get($activity->metadata, 'reason') }}</p>@endif<p class="mt-1 text-xs text-slate-500">{{ $activity->user->name }} · {{ $activity->created_at->format('d F Y, h:i A') }}</p></article>@endforeach</div>
            </section>
        @endif
    </div>
@endsection

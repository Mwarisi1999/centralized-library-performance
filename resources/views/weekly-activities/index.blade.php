@extends('layouts.app')
@section('title', 'Weekly Activities')
@section('section-label', 'Activity Monitoring')
@section('page-title', 'Weekly Activities')

@section('content')
<div class="mx-auto max-w-screen-2xl" data-weekly-activities>
    <header class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h2 class="text-3xl font-bold text-slate-950">Weekly Activities</h2>
            <p class="mt-2 max-w-2xl text-slate-600">Track recorded work across the week and quickly identify completed, in-progress, blocked, and missing weekday activities.</p>
        </div>
        <a href="{{ route('work-entries.create') }}" class="inline-flex w-fit items-center justify-center rounded-xl bg-busitema-blue px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-busitema-deep-blue">Record activity</a>
    </header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col justify-between gap-4 border-b border-slate-200 px-5 py-5 sm:px-6 lg:flex-row lg:items-center">
            <div>
                <h3 class="text-lg font-bold text-slate-950">Weekly activity overview</h3>
                <p class="mt-1 text-sm text-slate-500">Choose a month and one of its calendar weeks to review recorded work.</p>
            </div>

            <form method="GET" action="{{ route('weekly-activities.index') }}" class="flex flex-wrap gap-2" data-weekly-filter>
                <label class="sr-only" for="weekly-month">Month</label>
                <select id="weekly-month" name="month" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-busitema-blue focus:ring-2 focus:ring-busitema-blue/20" data-weekly-month>
                    @foreach(range(1, 12) as $option)
                        <option value="{{ $option }}" @selected($month === $option)>{{ Carbon\CarbonImmutable::create(null, $option, 1)->format('F') }}</option>
                    @endforeach
                </select>

                <label class="sr-only" for="weekly-year">Year</label>
                <select id="weekly-year" name="year" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-busitema-blue focus:ring-2 focus:ring-busitema-blue/20" data-weekly-year>
                    @foreach($years as $option)
                        <option value="{{ $option }}" @selected($year === $option)>{{ $option }}</option>
                    @endforeach
                </select>

                <label class="sr-only" for="weekly-week">Calendar week</label>
                <select id="weekly-week" name="week" class="min-w-44 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-busitema-blue focus:ring-2 focus:ring-busitema-blue/20" data-weekly-week>
                    @foreach($weeks as $week)
                        <option value="{{ $week['value'] }}" @selected($selectedWeek === $week['value'])>{{ $week['label'] }}</option>
                    @endforeach
                </select>
                <noscript><button class="rounded-lg bg-busitema-blue px-4 py-2 text-sm font-bold text-white">View week</button></noscript>
            </form>
        </div>

        <div class="grid divide-y divide-slate-100 md:grid-cols-7 md:divide-x md:divide-y-0">
            @foreach($days as $day)
                <button
                    type="button"
                    data-weekly-day-open
                    data-day-target="{{ $day['date']->format('Y-m-d') }}"
                    class="min-h-48 p-4 text-left transition hover:bg-busitema-blue/5 focus:z-10 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-busitema-blue"
                    aria-label="View activity summary for {{ $day['date']->format('l, j F Y') }}"
                >
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $day['date']->format('D') }}</p>
                    <p class="mt-1 text-sm font-bold text-slate-950">{{ $day['date']->format('j M') }}</p>
                    <div class="mt-4 space-y-3">
                        @forelse($day['entries']->take(3) as $entry)
                            @php($status = $day['statuses']->get($loop->index))
                            <div class="flex gap-2">
                                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $status['dot'] }}" aria-hidden="true"></span>
                                <p class="line-clamp-2 text-xs leading-5 text-slate-700">{{ $entry->work_description }}</p>
                            </div>
                        @empty
                            @if($day['is_weekday'])
                                <div class="flex gap-2"><span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-red-500" aria-hidden="true"></span><p class="text-xs leading-5 text-slate-500">No activity recorded</p></div>
                            @else
                                <p class="text-xs text-slate-400">No activity recorded</p>
                            @endif
                        @endforelse
                        @if($day['entries']->count() > 3)
                            <p class="text-xs font-bold text-busitema-blue">+{{ $day['entries']->count() - 3 }} more activities</p>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-x-5 gap-y-2 border-t border-slate-200 px-5 py-3 text-xs text-slate-600 sm:px-6">
            @foreach([
                ['bg-emerald-500', 'Completed'],
                ['bg-amber-500', 'In progress'],
                ['bg-slate-400', 'Not started'],
                ['bg-blue-500', 'Blocked'],
                ['bg-red-500', 'No weekday activity'],
            ] as [$colour, $label])
                <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $colour }}"></span>{{ $label }}</span>
            @endforeach
        </div>
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach([
            ['Completed', 'Work marked as completed.', 'bg-emerald-500'],
            ['In progress', 'Work that is still underway.', 'bg-amber-500'],
            ['Not started', 'Work recorded but not yet started.', 'bg-slate-400'],
            ['Blocked', 'Work currently waiting on support or action.', 'bg-blue-500'],
            ['Missing weekday record', 'A weekday with no activity recorded.', 'bg-red-500'],
        ] as [$title, $description, $colour])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3"><span class="h-3 w-3 rounded-full {{ $colour }}"></span><h3 class="font-bold text-slate-950">{{ $title }}</h3></div>
                <p class="mt-3 text-sm leading-6 text-slate-500">{{ $description }}</p>
            </article>
        @endforeach
    </section>

    <div data-weekly-modal class="fixed inset-0 z-[70] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="weekly-modal-title">
        <button type="button" data-weekly-modal-backdrop class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" aria-label="Close activity summary"></button>
        <section class="relative z-10 flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                <div><p class="text-xs font-bold uppercase tracking-wider text-busitema-blue">Daily summary</p><h3 id="weekly-modal-title" class="mt-1 text-xl font-bold text-slate-950" data-weekly-modal-title>Activity summary</h3></div>
                <button type="button" data-weekly-modal-close class="rounded-lg p-2 text-2xl leading-none text-slate-500 hover:bg-slate-100 hover:text-slate-900" aria-label="Close activity summary">&times;</button>
            </header>
            <div class="overflow-y-auto p-5 sm:p-6">
                @foreach($days as $day)
                    <div data-day-summary="{{ $day['date']->format('Y-m-d') }}" data-day-label="{{ $day['date']->format('l, j F Y') }}" class="hidden space-y-4">
                        @forelse($day['entries'] as $entry)
                            @php($status = $day['statuses']->get($loop->index))
                            <article class="rounded-xl border border-slate-200 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex min-w-0 gap-3"><span class="mt-1.5 h-3 w-3 shrink-0 rounded-full {{ $status['dot'] }}"></span><div><h4 class="font-bold text-slate-950">{{ $entry->work_description }}</h4><p class="mt-1 text-xs text-slate-500">{{ $entry->entry_code }} · {{ $entry->project?->title ?? 'Project unavailable' }}</p></div></div>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $status['label'] }}</span>
                                </div>
                                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Task</dt><dd class="mt-1 text-slate-700">{{ $entry->task?->title ?? 'Task unavailable' }}</dd></div>
                                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Time worked</dt><dd class="mt-1 text-slate-700">{{ Carbon\CarbonImmutable::parse($entry->start_time)->format('H:i') }}–{{ Carbon\CarbonImmutable::parse($entry->end_time)->format('H:i') }} · {{ $entry->formatted_duration }}</dd></div>
                                    @if($entry->output_deliverable)<div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Output</dt><dd class="mt-1 text-slate-700">{{ $entry->output_deliverable }}</dd></div>@endif
                                    @if($entry->challenge_encountered)<div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Challenge</dt><dd class="mt-1 text-slate-700">{{ $entry->challenge_encountered }}</dd></div>@endif
                                </dl>
                                <a href="{{ route('work-entries.show', $entry) }}" class="mt-4 inline-flex text-sm font-bold text-busitema-blue hover:text-busitema-deep-blue">View full activity</a>
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center">
                                <span class="mx-auto block h-3 w-3 rounded-full {{ $day['is_weekday'] ? 'bg-red-500' : 'bg-slate-300' }}"></span>
                                <h4 class="mt-4 font-bold text-slate-900">No activity</h4>
                                <p class="mt-1 text-sm text-slate-500">No activity was recorded for this day.</p>
                            </div>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
@endsection

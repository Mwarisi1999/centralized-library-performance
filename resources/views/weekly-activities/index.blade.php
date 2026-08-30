@extends('layouts.app')
@section('title', 'Weekly Activities')
@section('section-label', 'Activity Monitoring')
@section('page-title', 'Weekly Activities')

@section('content')
<div class="mx-auto max-w-screen-2xl">
    <header class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><h2 class="text-3xl font-bold">Weekly Activities</h2><p class="mt-2 max-w-2xl text-slate-600">Review recorded work across each week and identify completed, ongoing, or missing weekday activity.</p></div>
        <a href="{{ route('work-entries.create') }}" class="w-fit rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white">+ Record Activity</a>
    </header>
    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('weekly-activities.index') }}" class="grid gap-4 sm:grid-cols-3 lg:grid-cols-[1fr_1fr_2fr_auto] lg:items-end">
            <label class="text-sm font-semibold">Month<select name="month" class="mt-2 w-full rounded-xl border-slate-300">@foreach(range(1,12) as $option)<option value="{{ $option }}" @selected($month===$option)>{{ Carbon\CarbonImmutable::create(null,$option,1)->format('F') }}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">Year<select name="year" class="mt-2 w-full rounded-xl border-slate-300">@foreach(range(today()->year + 1, today()->year - 5) as $option)<option value="{{ $option }}" @selected($year===$option)>{{ $option }}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">Week<select name="week" class="mt-2 w-full rounded-xl border-slate-300">@foreach($weeks as $week)<option value="{{ $week['value'] }}" @selected($selectedWeek===$week['value'])>Week {{ $week['value'] }}: {{ $week['label'] }}</option>@endforeach</select></label>
            <button class="rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white">View Week</button>
        </form>
    </section>
    <section class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        @foreach([['Activities',$summary['entries']],['Hours',App\Models\WorkEntry::formatMinutes($summary['minutes'])],['Completed',$summary['completed']],['In Progress',$summary['in_progress']],['Missing Weekdays',$summary['missing_weekdays']]] as [$label,$value])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold">{{ $value }}</p></article>
        @endforeach
    </section>
    <section class="grid gap-4 xl:grid-cols-7">
        @foreach($days as $day)
            <article class="min-h-64 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="border-b border-slate-200 pb-3"><p class="font-bold">{{ $day['date']->format('l') }}</p><p class="mt-1 text-xs text-slate-500">{{ $day['date']->format('d M Y') }} · {{ App\Models\WorkEntry::formatMinutes($day['minutes']) }}</p></div>
                <div class="mt-3 space-y-3">
                    @forelse($day['entries'] as $entry)
                        <a href="{{ route('work-entries.show', $entry) }}" class="block rounded-xl bg-slate-50 p-3 hover:bg-blue-50"><p class="line-clamp-2 text-sm font-semibold">{{ $entry->work_description }}</p><p class="mt-2 text-xs text-slate-500">{{ $entry->task->title }}</p><div class="mt-2 flex items-center justify-between gap-2"><span class="text-xs font-semibold capitalize text-blue-700">{{ str($entry->activity_status)->replace('_',' ') }}</span><span class="text-xs text-slate-500">{{ $entry->formatted_duration }}</span></div></a>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs {{ $day['is_weekday'] ? 'text-red-700' : 'text-slate-500' }}">{{ $day['is_weekday'] ? 'No activity recorded' : 'No activity' }}</p>
                    @endforelse
                </div>
            </article>
        @endforeach
    </section>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Daily Activities')
@section('section-label', 'Activity Management')
@section('page-title', 'Daily Activities')

@section('content')
<div class="mx-auto max-w-screen-2xl">
    <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><h2 class="text-3xl font-bold">Daily Activities</h2><p class="mt-2 text-slate-600">Record work performed on your assigned tasks and follow its daily status.</p></div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('weekly-activities.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-700">Weekly Overview</a>
            <a href="{{ route('printable-timesheet.index') }}" class="rounded-xl border border-emerald-700 px-5 py-3 font-semibold text-emerald-800">Printable Timesheet</a>
            <a href="{{ route('work-entries.create') }}" class="rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white">+ Record Activity</a>
        </div>
    </header>
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5"><h3 class="text-lg font-bold">Recent Activities</h3></div>
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500"><tr>@foreach(['Code','Date','Due Date','Project / Task','Activity','Priority','Status','Time','Hours','View'] as $heading)<th class="whitespace-nowrap px-5 py-3.5">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($entries as $entry)
                <tr>
                    <td class="px-5 py-4 font-mono text-xs font-bold">{{ $entry->entry_code }}</td>
                    <td class="whitespace-nowrap px-5 py-4">{{ $entry->work_date->format('d M Y') }}</td>
                    <td class="whitespace-nowrap px-5 py-4">{{ $entry->due_date?->format('d M Y') ?? '—' }}</td>
                    <td class="min-w-60 px-5 py-4"><span class="font-semibold">{{ $entry->project->title }}</span><p class="mt-1 text-xs text-slate-500">{{ $entry->task->task_code }} — {{ $entry->task->title }}</p></td>
                    <td class="min-w-72 px-5 py-4"><p class="line-clamp-2">{{ $entry->work_description }}</p></td>
                    <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-700">{{ $entry->priority }}</span></td>
                    <td class="px-5 py-4"><span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold capitalize text-blue-700">{{ str($entry->activity_status)->replace('_', ' ') }}</span></td>
                    <td class="whitespace-nowrap px-5 py-4">{{ Carbon\Carbon::parse($entry->start_time)->format('H:i') }}–{{ Carbon\Carbon::parse($entry->end_time)->format('H:i') }}</td>
                    <td class="whitespace-nowrap px-5 py-4 font-semibold">{{ $entry->formatted_duration }}</td>
                    <td class="px-5 py-4"><a href="{{ route('work-entries.show', $entry) }}" class="font-semibold text-emerald-700">View</a></td>
                </tr>
            @empty
                <tr><td colspan="10" class="px-6 py-14 text-center text-slate-500">No daily activities recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @if($entries->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $entries->links() }}</div>@endif
    </section>
</div>
@endsection

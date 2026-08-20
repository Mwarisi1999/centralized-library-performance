@extends('layouts.app')
@section('title', 'Campus Monthly Reports')
@section('page-title', 'Campus Monthly Reports')
@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-sm font-bold uppercase tracking-wider text-emerald-700">Formal campus reporting</p><h2 class="mt-1 text-3xl font-bold">{{ $campus->name }} Monthly Reports</h2><p class="mt-2 text-slate-600">Prepare a live campus consolidation or open a finalized historical snapshot.</p></div>
    </header>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold">Prepare Report</h3>
        <form method="GET" action="{{ route('campus-reports.create') }}" class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end">
            <label class="flex-1 text-sm font-semibold">Month<select name="month" class="mt-2 w-full rounded-xl border-slate-300">@foreach(range(1,12) as $month)<option value="{{ $month }}" @selected(today()->month === $month)>{{ Carbon\CarbonImmutable::create(2000,$month,1)->format('F') }}</option>@endforeach</select></label>
            <label class="flex-1 text-sm font-semibold">Year<select name="year" class="mt-2 w-full rounded-xl border-slate-300">@foreach(range(today()->year + 1,2000) as $year)<option value="{{ $year }}" @selected(today()->year === $year)>{{ $year }}</option>@endforeach</select></label>
            <button class="rounded-xl bg-emerald-800 px-6 py-3 font-semibold text-white">Generate Report</button>
        </form>
    </section>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5"><h3 class="text-lg font-bold">Finalized Report History</h3></div>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Report Code</th><th class="px-5 py-3">Period</th><th class="px-5 py-3">Finalized By</th><th class="px-5 py-3">Finalized</th><th class="px-5 py-3">Status</th><th></th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($history as $item)<tr><td class="px-5 py-4 font-mono font-bold text-emerald-700">{{ $item->report_code }}</td><td class="px-5 py-4">{{ Carbon\CarbonImmutable::create($item->reporting_year,$item->reporting_month,1)->format('F Y') }}</td><td class="px-5 py-4">{{ $item->finalizer?->name }}</td><td class="px-5 py-4">{{ $item->finalized_at->format('d M Y, H:i') }}</td><td class="px-5 py-4"><span class="rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-800">Finalized</span></td><td class="px-5 py-4"><a class="font-semibold text-emerald-700" href="{{ route('campus-reports.show',$item) }}">View</a></td></tr>
            @empty<tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No campus reports have been finalized.</td></tr>@endforelse
        </tbody></table></div><div class="p-4">{{ $history->links() }}</div>
    </section>
</div>
@endsection

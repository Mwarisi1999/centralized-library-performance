@extends('layouts.app')
@section('title', 'Reports Awaiting My Review')
@section('section-label', 'Reports')
@section('page-title', 'Reports Awaiting My Review')
@section('content')
<div class="mx-auto max-w-screen-2xl">
    <header class="mb-7"><h2 class="text-3xl font-bold text-slate-900">Reports Awaiting My Review</h2><p class="mt-2 text-slate-600">Monthly reports submitted directly to you as the recorded supervisor.</p></header>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500"><tr>@foreach(['Report Code','Staff Member','Position','Campus','Library','Reporting Period','Submitted At','Status','Action'] as $heading)<th class="whitespace-nowrap px-5 py-3.5">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">@forelse($reports as $report)<tr><td class="px-5 py-4 font-mono text-xs font-bold text-emerald-700">{{ $report->report_code }}</td><td class="whitespace-nowrap px-5 py-4 font-semibold">{{ $report->user->name }}</td><td class="px-5 py-4">{{ $report->user->staffProfile?->position?->name ?? '—' }}</td><td class="px-5 py-4">{{ $report->user->staffProfile?->campus?->name ?? '—' }}</td><td class="px-5 py-4">{{ $report->user->staffProfile?->library?->name ?? '—' }}</td><td class="whitespace-nowrap px-5 py-4">{{ Carbon\CarbonImmutable::create($report->reporting_year,$report->reporting_month,1)->format('F Y') }}</td><td class="whitespace-nowrap px-5 py-4">{{ $report->submitted_at?->format('d M Y, h:i A') }}</td><td class="px-5 py-4"><span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">Pending Review</span></td><td class="px-5 py-4"><a href="{{ route('monthly-reports.reviews.show',$report) }}" class="font-semibold text-emerald-700">View / Review</a></td></tr>@empty<tr><td colspan="9" class="px-6 py-14 text-center text-slate-500">No monthly reports are awaiting your review.</td></tr>@endforelse</tbody></table></div>
        @if($reports->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $reports->links() }}</div>@endif
    </section>
</div>
@endsection

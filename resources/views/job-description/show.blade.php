@extends('layouts.app')

@section('title', 'My Job Description')
@section('section-label', 'My Work')
@section('page-title', 'My Job Description')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    @if($jobDetail)
        <section class="relative overflow-hidden rounded-2xl bg-busitema-blue p-6 text-white shadow-sm sm:p-8">
            <div class="absolute inset-y-0 right-0 w-2 bg-busitema-gold" aria-hidden="true"></div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-white/70">Current assigned position</p>
            <div class="mt-3 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $position->name }}</h2>
                    <p class="mt-3 max-w-3xl leading-7 text-white/80">This is the current job description attached to your staff position. Use these duties to guide your assigned work and daily activity records.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="rounded-full bg-white/10 px-3 py-1.5 ring-1 ring-white/20">{{ $position->code }}</span>
                    @if($jobDetail->salary_scale)
                        <span class="rounded-full bg-busitema-gold px-3 py-1.5 text-busitema-navy">Salary scale {{ $jobDetail->salary_scale }}</span>
                    @endif
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Employment assignment">
            @foreach([
                ['System role', $user->getRoleNames()->join(', ') ?: 'Not assigned'],
                ['Reports to', $jobDetail->reports_to ?: 'Not specified'],
                ['Responsible for', $jobDetail->responsible_for ?: 'Not specified'],
                ['Campus / Library', collect([$profile?->campus?->name, $profile?->library?->name])->filter()->join(' · ') ?: 'Not assigned'],
            ] as [$label, $value])
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                    <p class="mt-2 font-semibold leading-6 text-slate-900">{{ $value }}</p>
                </article>
            @endforeach
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="job-purpose-heading">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-busitema-blue">Role overview</p>
            <h3 id="job-purpose-heading" class="mt-2 text-2xl font-bold text-slate-950">Job purpose</h3>
            <p class="mt-4 max-w-4xl text-base leading-8 text-slate-700">{{ $jobDetail->job_purpose }}</p>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="duties-heading">
            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-6 py-5 sm:flex-row sm:items-end sm:justify-between sm:px-8">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-busitema-blue">Work guidance</p>
                    <h3 id="duties-heading" class="mt-2 text-2xl font-bold text-slate-950">Duties and responsibilities</h3>
                </div>
                <p class="text-sm font-semibold text-slate-500">{{ count($jobDetail->duties) }} {{ Str::plural('duty', count($jobDetail->duties)) }}</p>
            </div>

            <ol class="divide-y divide-slate-100">
                @foreach($jobDetail->duties as $index => $duty)
                    <li class="flex gap-4 px-6 py-5 sm:px-8">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-sm font-bold text-busitema-blue">{{ $index + 1 }}</span>
                        <div class="min-w-0 flex-1 sm:flex sm:items-start sm:justify-between sm:gap-5">
                            <p class="leading-7 text-slate-700">{{ $duty }}</p>
                            @can('create', App\Models\WorkEntry::class)
                                <a href="{{ route('work-entries.create', ['duty' => $index]) }}" class="mt-3 inline-flex shrink-0 items-center rounded-lg border border-blue-200 px-3 py-2 text-xs font-bold text-blue-700 transition hover:bg-blue-50 sm:mt-0">Record activity</a>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <p class="text-center text-xs text-slate-500">Job description last updated {{ $jobDetail->updated_at->format('d M Y') }}. Contact your administrator if your assigned position is incorrect.</p>
    @else
        <section class="rounded-2xl border border-amber-200 bg-white p-8 text-center shadow-sm sm:p-12">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-2xl" aria-hidden="true">!</div>
            <h2 class="mt-5 text-2xl font-bold text-slate-950">No job description is currently available</h2>
            @if($position)
                <p class="mx-auto mt-3 max-w-2xl leading-7 text-slate-600">Your assigned position is <strong>{{ $position->name }}</strong>, but no job description has been recorded for it yet. Please contact an administrator.</p>
            @else
                <p class="mx-auto mt-3 max-w-2xl leading-7 text-slate-600">No position is assigned to your staff profile yet. Please contact an administrator so your position and job description can be linked.</p>
            @endif
            <a href="{{ route('profile.show') }}" class="mt-6 inline-flex rounded-xl bg-busitema-blue px-5 py-2.5 font-semibold text-white">View my profile</a>
        </section>
    @endif
</div>
@endsection

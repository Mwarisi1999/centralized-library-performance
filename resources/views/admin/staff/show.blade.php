@extends('layouts.app')

@section('title', $user->name.' - Staff Profile')
@section('section-label', 'User Management')
@section('page-title', 'Staff Profile')

@section('content')
@php
    $profile = $user->staffProfile;
    $nameParts = collect(preg_split('/\s+/', trim($user->name)))
        ->reject(fn ($part) => in_array(strtolower(rtrim($part, '.')), ['dr', 'mr', 'mrs', 'ms', 'prof'], true))
        ->values();
    $initials = $nameParts->isEmpty()
        ? '?'
        : str($nameParts->first())->substr(0, 1).($nameParts->count() > 1 ? str($nameParts->last())->substr(0, 1) : '');
    $statusClasses = match($user->account_status) {
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'pending' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'suspended' => 'bg-red-50 text-red-700 ring-red-600/20',
        default => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };
    $formatEmploymentType = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : '—';
@endphp

<div class="mx-auto max-w-screen-2xl">
    <header class="mb-8">
        <a href="{{ route('admin.staff.index') }}" class="inline-flex text-sm font-semibold text-emerald-700 hover:text-emerald-900">&larr; Back to Staff Management</a>
        <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">User Management</p>
                <h1 class="mt-2 text-3xl font-bold">Staff Profile</h1>
                <p class="mt-2 text-slate-600">View account, organizational and employment information.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @if($user->account_status === 'pending')
                    @can('create staff')
                        <form method="POST" action="{{ route('admin.staff.resend-invitation', $user) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-emerald-700 bg-white px-5 py-3 font-semibold text-emerald-800 hover:bg-emerald-50">Resend Invitation</button>
                        </form>
                    @endcan
                @endif
                @can('edit staff')
                    <a href="{{ route('admin.staff.edit', $user) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white shadow-sm hover:bg-emerald-900">Edit Staff</a>
                @endcan
            </div>
        </div>
    </header>

    @if(session('success'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700">{{ session('success') }}</div>
    @endif

    @if($errors->has('invitation'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-700">{{ $errors->first('invitation') }}</div>
    @endif

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
            <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-2xl font-bold uppercase text-emerald-800 ring-4 ring-white shadow-sm" aria-label="Initials {{ $initials }}">
                {{ $initials }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900">{{ $user->name }}</h2>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($user->roles as $role)
                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">{{ $role->name }}</span>
                            @empty
                                <span class="text-sm text-slate-500">No system role assigned</span>
                            @endforelse
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusClasses }}">{{ ucfirst($user->account_status) }}</span>
                        </div>
                    </div>
                    <p class="font-mono text-sm font-semibold text-slate-600">{{ $profile?->staff_number ?? '—' }}</p>
                </div>
                <div class="mt-5 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                    <p><span class="font-semibold text-slate-800">Email:</span> {{ $user->email }}</p>
                    <p><span class="font-semibold text-slate-800">Phone:</span> {{ $profile?->phone ?? '—' }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Organizational Information</h2>
            <dl class="mt-5 space-y-4">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Campus</dt><dd class="mt-1 font-medium text-slate-800">{{ $profile?->campus?->name ?? 'University-wide' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Library</dt><dd class="mt-1 font-medium text-slate-800">{{ $profile?->library?->name ?? 'University-wide' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Position</dt><dd class="mt-1 font-medium text-slate-800">{{ $profile?->position?->name ?? '—' }}</dd></div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Supervisor</dt>
                    <dd class="mt-1 font-medium text-slate-800">{{ $profile?->supervisor?->name ?? '—' }}</dd>
                    @if($profile?->supervisor?->roles->isNotEmpty())
                        <dd class="mt-1 text-sm text-slate-500">{{ $profile->supervisor->roles->pluck('name')->join(', ') }}</dd>
                    @endif
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Employment Information</h2>
            <dl class="mt-5 space-y-4">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employment Type</dt><dd class="mt-1 font-medium text-slate-800">{{ $formatEmploymentType($profile?->employment_type) }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Start Date</dt><dd class="mt-1 font-medium text-slate-800">{{ $profile?->start_date?->format('j F Y') ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Staff Number</dt><dd class="mt-1 font-mono text-sm font-semibold text-slate-800">{{ $profile?->staff_number ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Account Status</dt><dd class="mt-2"><span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusClasses }}">{{ ucfirst($user->account_status) }}</span></dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">System Access</h2>
            <dl class="mt-5 space-y-4">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">System Role</dt><dd class="mt-1 font-medium text-slate-800">{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Account Status</dt><dd class="mt-2"><span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusClasses }}">{{ ucfirst($user->account_status) }}</span></dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email Verification Status</dt><dd class="mt-1 font-medium {{ $user->email_verified_at ? 'text-emerald-700' : 'text-amber-700' }}">{{ $user->email_verified_at ? 'Verified' : 'Not verified' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Account Created</dt><dd class="mt-1 font-medium text-slate-800">{{ $user->created_at?->format('j F Y, g:i A') ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last Updated</dt><dd class="mt-1 font-medium text-slate-800">{{ $user->updated_at?->format('j F Y, g:i A') ?? '—' }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="text-xl font-bold">Supervision</h2>

        <div class="mt-6 grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Reports To</h3>
                @if($profile?->supervisor)
                    <div class="mt-3 rounded-xl bg-slate-50 p-4">
                        <a href="{{ route('admin.staff.show', $profile->supervisor) }}" class="font-semibold text-emerald-700 hover:text-emerald-900">{{ $profile->supervisor->name }}</a>
                        <p class="mt-1 text-sm text-slate-600">{{ $profile->supervisor->roles->pluck('name')->join(', ') ?: 'No role assigned' }}</p>
                        <p class="mt-2 text-sm text-slate-500">
                            {{ $profile->supervisor->staffProfile?->campus?->name ?? 'University-wide' }}
                            <span aria-hidden="true">&middot;</span>
                            {{ $profile->supervisor->staffProfile?->library?->name ?? 'University-wide' }}
                        </p>
                    </div>
                @else
                    <p class="mt-3 text-slate-600">—</p>
                @endif
            </div>

            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Direct Reports</h3>
                @if($directReports->isEmpty())
                    <div class="mt-3 rounded-xl border border-dashed border-slate-300 px-5 py-8 text-center text-sm text-slate-500">No staff members currently report directly to this user.</div>
                @else
                    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-[760px] w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    @foreach(['Name', 'Staff Number', 'Role', 'Position', 'Campus / Library'] as $heading)
                                        <th scope="col" class="px-4 py-3 font-semibold">{{ $heading }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach($directReports as $report)
                                    @if($report->user)
                                        <tr>
                                            <td class="px-4 py-3"><a href="{{ route('admin.staff.show', $report->user) }}" class="font-semibold text-emerald-700 hover:text-emerald-900">{{ $report->user->name }}</a></td>
                                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-slate-700">{{ $report->staff_number }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $report->user->roles->pluck('name')->join(', ') ?: '—' }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $report->position?->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $report->campus?->name ?? 'University-wide' }} / {{ $report->library?->name ?? 'University-wide' }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection

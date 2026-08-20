@extends('layouts.app')

@section('title', 'Staff Management')
@section('section-label', 'User Management')
@section('page-title', 'Staff Management')

@section('content')
<div class="mx-auto max-w-screen-2xl">
    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">User Management</p>
            <h1 class="mt-2 text-3xl font-bold">Staff Management</h1>
            <p class="mt-2 max-w-3xl text-slate-600">Manage library staff, librarians, interns and system users across all campuses.</p>
        </div>
        @can('create staff')
            <a href="{{ route('admin.staff.create') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white shadow-sm hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
                + Add Staff Member
            </a>
        @endcan
    </div>

    <section aria-label="Staff summary" class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach([
            ['Total Users', $summary['total']],
            ['Campus Librarians', $summary['campus_librarians']],
            ['Staff', $summary['staff']],
            ['Interns', $summary['interns']],
            ['Active Accounts', $summary['active']],
            ['Pending Accounts', $summary['pending']],
        ] as [$label, $value])
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" action="{{ route('admin.staff.index') }}" class="border-b border-slate-200 p-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="md:col-span-2 xl:col-span-4">
                    <label for="search" class="sr-only">Search staff</label>
                    <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Search staff..." class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                </div>

                <label class="text-sm font-semibold text-slate-700">Campus
                    <select name="campus_id" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal">
                        <option value="">All campuses</option>
                        @foreach($campuses as $campus)
                            <option value="{{ $campus->id }}" @selected((string) request('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm font-semibold text-slate-700">Library
                    <select name="library_id" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal">
                        <option value="">All libraries</option>
                        @foreach($libraries as $library)
                            <option value="{{ $library->id }}" @selected((string) request('library_id') === (string) $library->id)>{{ $library->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm font-semibold text-slate-700">Role
                    <select name="role" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal">
                        <option value="">All roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm font-semibold text-slate-700">Position
                    <select name="position_id" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal">
                        <option value="">All positions</option>
                        @foreach($positions as $position)
                            <option value="{{ $position->id }}" @selected((string) request('position_id') === (string) $position->id)>{{ $position->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm font-semibold text-slate-700">Employment Type
                    <select name="employment_type" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal">
                        <option value="">All employment types</option>
                        @foreach(['permanent' => 'Permanent', 'contract' => 'Contract', 'graduate_fellow' => 'Graduate Fellow', 'intern' => 'Intern', 'temporary' => 'Temporary', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('employment_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm font-semibold text-slate-700">Account Status
                    <select name="account_status" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal">
                        <option value="">All statuses</option>
                        @foreach(['active', 'pending', 'suspended', 'inactive'] as $status)
                            <option value="{{ $status }}" @selected(request('account_status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Apply filters</button>
                @if(request()->hasAny(['search', 'campus_id', 'library_id', 'role', 'position_id', 'employment_type', 'account_status']))
                    <a href="{{ route('admin.staff.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900">Clear filters</a>
                @endif
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-[1180px] w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        @foreach(['Staff No.', 'Name', 'Role', 'Campus', 'Library', 'Position', 'Supervisor', 'Employment Type', 'Account Status', 'Actions'] as $heading)
                            <th scope="col" class="whitespace-nowrap px-5 py-4 font-semibold">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($staff as $member)
                        @php
                            $profile = $member->staffProfile;
                            $statusClasses = match($member->account_status) {
                                'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                'pending' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                'suspended' => 'bg-red-50 text-red-700 ring-red-600/20',
                                default => 'bg-slate-100 text-slate-600 ring-slate-500/20',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs font-semibold text-slate-700">{{ $profile?->staff_number ?? '—' }}</td>
                            <td class="px-5 py-4">
                                <p class="whitespace-nowrap font-semibold text-slate-900">{{ $member->name }}</p>
                                <p class="mt-1 whitespace-nowrap text-xs text-slate-500">{{ $member->email }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse($member->roles as $role)
                                        <span class="whitespace-nowrap rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">{{ $role->name }}</span>
                                    @empty
                                        <span class="text-slate-400">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $profile?->campus?->name ?? 'University-wide' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $profile?->library?->name ?? 'University-wide' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $profile?->position?->name ?? '—' }}</td>
                            <td class="px-5 py-4">
                                @if($profile?->supervisor)
                                    <p class="whitespace-nowrap font-medium text-slate-800">{{ $profile->supervisor->name }}</p>
                                    @if($profile->supervisor->roles->isNotEmpty())
                                        <p class="mt-1 whitespace-nowrap text-xs text-slate-500">Supervisor: {{ $profile->supervisor->roles->pluck('name')->join(', ') }}</p>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if($profile?->employment_type)
                                    <span class="whitespace-nowrap rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 ring-1 ring-inset ring-sky-600/20">{{ str($profile->employment_type)->replace('_', ' ')->title() }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <span class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusClasses }}">{{ ucfirst($member->account_status) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <a href="{{ route('admin.staff.show', $member) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900">View</a>
                                <span class="mx-2 text-slate-300">|</span>
                                @can('edit staff')
                                    <a href="{{ route('admin.staff.edit', $member) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900">Edit</a>
                                @else
                                    <span class="text-sm font-medium text-slate-400">Edit</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-16 text-center">
                                <p class="text-lg font-semibold text-slate-800">No staff members found.</p>
                                <p class="mt-2 text-sm text-slate-500">Try adjusting your search or filters.</p>
                                @if(request()->hasAny(['search', 'campus_id', 'library_id', 'role', 'position_id', 'employment_type', 'account_status']))
                                    <a href="{{ route('admin.staff.index') }}" class="mt-5 inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Clear filters</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($staff->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">{{ $staff->links() }}</div>
        @endif
    </section>
</div>
@endsection

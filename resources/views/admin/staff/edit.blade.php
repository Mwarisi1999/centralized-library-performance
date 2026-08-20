@extends('layouts.app')

@section('title', 'Edit Staff Account')
@section('section-label', 'User Management')
@section('page-title', 'Edit Staff Account')

@section('content')
@php($profile = $user->staffProfile)

<div class="mx-auto max-w-5xl">
    <header class="mb-8">
        <a href="{{ route('admin.staff.show', $user) }}" class="inline-flex text-sm font-semibold text-emerald-700 hover:text-emerald-900">&larr; Back to Staff Profile</a>
        <p class="mt-6 text-sm font-semibold uppercase tracking-wide text-emerald-700">User Management</p>
        <h1 class="mt-2 text-3xl font-bold">Edit Staff Account</h1>
        <p class="mt-2 text-slate-600">Update the user's organizational and employment information.</p>
    </header>

    @if($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-700" role="alert">
            <p class="font-semibold">Please correct the following:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.staff.update', $user) }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @csrf
        @method('PUT')

        <div class="mb-7 rounded-xl bg-slate-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Staff Number</p>
            <p class="mt-1 font-mono text-sm font-semibold text-slate-800">{{ $profile?->staff_number ?? '—' }}</p>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <label class="block text-sm font-semibold text-slate-700">Full name
                <input name="name" type="text" required value="{{ old('name', $user->name) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600">
            </label>

            <label class="block text-sm font-semibold text-slate-700">Email address
                <input name="email" type="email" required value="{{ old('email', $user->email) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600">
            </label>

            <label class="block text-sm font-semibold text-slate-700">Phone
                <input name="phone" type="text" value="{{ old('phone', $profile?->phone) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600">
            </label>

            <label class="block text-sm font-semibold text-slate-700">System role
                <select id="role" name="role" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600">
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" @selected(old('role', $user->roles->first()?->name) === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm font-semibold text-slate-700">Campus
                <select id="campus" name="campus_id" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600">
                    <option value="">University-wide / Not applicable</option>
                    @foreach($campuses as $campus)
                        <option value="{{ $campus->id }}" @selected((string) old('campus_id', $profile?->campus_id) === (string) $campus->id)>{{ $campus->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm font-semibold text-slate-700">Library
                <select id="library" name="library_id" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600">
                    <option value="">University-wide / Not applicable</option>
                    @foreach($libraries as $library)
                        <option value="{{ $library->id }}" data-campus="{{ $library->campus_id }}" @selected((string) old('library_id', $profile?->library_id) === (string) $library->id)>{{ $library->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm font-semibold text-slate-700">Position
                <select name="position_id" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600">
                    <option value="">Select position</option>
                    @foreach($positions as $position)
                        <option value="{{ $position->id }}" @selected((string) old('position_id', $profile?->position_id) === (string) $position->id)>{{ $position->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm font-semibold text-slate-700">Supervisor
                <select id="supervisor" name="supervisor_id" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600">
                    <option value="">No supervisor / Not applicable</option>
                    @foreach($supervisors as $supervisor)
                        <option
                            value="{{ $supervisor->id }}"
                            data-roles="{{ $supervisor->roles->pluck('name')->join('|') }}"
                            data-campus="{{ $supervisor->staffProfile?->campus_id }}"
                            data-library="{{ $supervisor->staffProfile?->library_id }}"
                            @selected((string) old('supervisor_id', $profile?->supervisor_id) === (string) $supervisor->id)
                        >
                            {{ $supervisor->name }} — {{ $supervisor->roles->pluck('name')->join(', ') }}{{ $supervisor->staffProfile?->campus ? ' · '.$supervisor->staffProfile->campus->name : '' }}
                        </option>
                    @endforeach
                </select>
                <span class="mt-2 block text-xs font-normal text-slate-500">Choices are limited by role and organizational assignment.</span>
            </label>

            <label class="block text-sm font-semibold text-slate-700">Employment type
                <select name="employment_type" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600">
                    <option value="">Select type</option>
                    @foreach(['permanent' => 'Permanent', 'contract' => 'Contract', 'graduate_fellow' => 'Graduate Fellow', 'intern' => 'Intern', 'temporary' => 'Temporary', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('employment_type', $profile?->employment_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm font-semibold text-slate-700">Start date
                <input name="start_date" type="date" value="{{ old('start_date', $profile?->start_date?->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600">
            </label>

            <label class="block text-sm font-semibold text-slate-700">Account status
                <select name="account_status" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600">
                    @foreach(['pending', 'active', 'suspended', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(old('account_status', $user->account_status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('admin.staff.show', $user) }}" class="inline-flex items-center justify-center rounded-xl px-5 py-3 font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
            <button type="submit" class="rounded-xl bg-emerald-800 px-6 py-3 font-semibold text-white hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">Update Staff Account</button>
        </div>
    </form>
</div>

<script>
    (() => {
        const campus = document.getElementById('campus');
        const library = document.getElementById('library');
        const role = document.getElementById('role');
        const supervisor = document.getElementById('supervisor');

        const filterLibraries = () => {
            const campusId = campus.value;

            Array.from(library.options).forEach((option) => {
                if (!option.value) return;
                option.hidden = !campusId || option.dataset.campus !== campusId;
            });

            if (!campusId || library.selectedOptions[0]?.hidden) library.value = '';
            filterSupervisors();
        };

        const filterSupervisors = () => {
            const allowedRoles = {
                'Campus Librarian': ['University Librarian'],
                'Staff': ['Campus Librarian'],
                'Intern': ['Staff', 'Campus Librarian'],
            }[role.value] || [];

            Array.from(supervisor.options).forEach((option) => {
                if (!option.value) return;
                const roles = option.dataset.roles.split('|');
                const roleMatches = roles.some((value) => allowedRoles.includes(value));
                const organizationMatches = role.value === 'Campus Librarian'
                    || (option.dataset.campus === campus.value
                        && (!option.dataset.library || option.dataset.library === library.value));
                option.hidden = !roleMatches || !organizationMatches;
            });

            if (supervisor.selectedOptions[0]?.hidden) supervisor.value = '';
        };

        campus.addEventListener('change', filterLibraries);
        library.addEventListener('change', filterSupervisors);
        role.addEventListener('change', filterSupervisors);
        filterLibraries();
    })();
</script>
@endsection

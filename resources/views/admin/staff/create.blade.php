@extends('layouts.app')

@section('title', 'Create Staff Account')
@section('section-label', 'User Management')
@section('page-title', 'Create Staff Account')

@section('content')
<div class="mx-auto max-w-5xl">

    <div class="mb-8">
        <a href="{{ route('admin.staff.index') }}" class="inline-flex items-center text-sm font-semibold text-emerald-700 hover:text-emerald-900">
            &larr; Back to Staff Management
        </a>

        <p class="text-sm font-semibold text-emerald-700">
            USER MANAGEMENT
        </p>

        <h1 class="text-3xl font-bold text-slate-900 mt-2">
            Create Staff Account
        </h1>

        <p class="text-slate-500 mt-2">
            Create an account and assign the user's organizational and system information.
        </p>
    </div>

    @if($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-700">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.staff.store') }}"
        class="bg-white rounded-2xl shadow-sm p-8"
    >
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Full name
                </label>

                <input
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                >
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Email address
                </label>

                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                >
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Phone
                </label>

                <input
                    type="text"
                    name="phone"
                    value="{{ old('phone') }}"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                >
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    System role
                </label>

                <select
                    name="role"
                    required
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                >
                    <option value="">Select role</option>

                    @foreach($roles as $role)
                        <option
                            value="{{ $role->name }}"
                            @selected(old('role') === $role->name)
                        >
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Campus
                </label>

                <select
                    name="campus_id"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                >
                    <option value="">University-wide / Not applicable</option>

                    @foreach($campuses as $campus)
                        <option
                            value="{{ $campus->id }}"
                            @selected(old('campus_id') == $campus->id)
                        >
                            {{ $campus->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Library
                </label>

                <select
                    name="library_id"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                >
                    <option value="">Select library</option>

                    @foreach($libraries as $library)
                        <option
                            value="{{ $library->id }}"
                            @selected(old('library_id') == $library->id)
                        >
                            {{ $library->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Position
                </label>

                <select
                    name="position_id"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                >
                    <option value="">Select position</option>

                    @foreach($positions as $position)
                        <option
                            value="{{ $position->id }}"
                            @selected(old('position_id') == $position->id)
                        >
                            {{ $position->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Supervisor
                </label>

                <select
                    name="supervisor_id"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                >
                    <option value="">No supervisor / Not applicable</option>

                    @foreach($supervisors as $supervisor)
                        <option
                            value="{{ $supervisor->id }}"
                            @selected(old('supervisor_id') == $supervisor->id)
                        >
                            {{ $supervisor->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Employment type
                </label>

                <select
                    name="employment_type"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                >
                    <option value="">Select type</option>
                    <option value="permanent" @selected(old('employment_type') === 'permanent')>Permanent</option>
                    <option value="contract" @selected(old('employment_type') === 'contract')>Contract</option>
                    <option value="graduate_fellow" @selected(old('employment_type') === 'graduate_fellow')>Graduate Fellow</option>
                    <option value="intern" @selected(old('employment_type') === 'intern')>Intern</option>
                    <option value="temporary" @selected(old('employment_type') === 'temporary')>Temporary</option>
                    <option value="other" @selected(old('employment_type') === 'other')>Other</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">
                    Start date
                </label>

                <input
                    type="date"
                    name="start_date"
                    value="{{ old('start_date') }}"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"
                >
            </div>

        </div>

        <div class="mt-8 flex justify-end">
            <button
                type="submit"
                class="rounded-xl bg-emerald-800 px-6 py-3 font-semibold text-white hover:bg-emerald-900"
            >
                Create Staff Account
            </button>
        </div>

    </form>

</div>
@endsection

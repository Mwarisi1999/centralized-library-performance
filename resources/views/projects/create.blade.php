@extends('layouts.app')

@section('title', 'Create Project')
@section('section-label', 'Performance Management')
@section('page-title', 'Create Project')

@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-8">
        <a href="{{ route('projects.index') }}" class="inline-flex text-sm font-semibold text-emerald-700 hover:text-emerald-900">&larr; Back to Projects</a>
        <h2 class="mt-5 text-3xl font-bold tracking-tight text-slate-900">Create Project</h2>
        <p class="mt-2 text-slate-600">Define the project scope, ownership, team and initial configuration.</p>
    </header>

    @if($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-700" role="alert">
            <p class="font-semibold">Please correct the following:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('projects.store') }}" class="space-y-6">
        @csrf

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h3 class="text-lg font-bold text-slate-900">Project information</h3>
            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                <label class="block text-sm font-semibold text-slate-700 md:col-span-2">Project Title
                    <input name="title" type="text" required value="{{ old('title') }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600">
                </label>
                <label class="block text-sm font-semibold text-slate-700">Project Category
                    <select name="project_category_id" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal">
                        <option value="">Select category</option>
                        @foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('project_category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold text-slate-700">Project Owner
                    <select name="owner_id" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal">
                        <option value="">Select owner</option>
                        @foreach($activeUsers as $activeUser)
                            <option value="{{ $activeUser->id }}" @selected((string) old('owner_id', $defaultOwnerId) === (string) $activeUser->id)>{{ $activeUser->name }} — {{ $activeUser->getRoleNames()->join(', ') ?: 'User' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold text-slate-700 md:col-span-2">Description
                    <textarea name="description" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal">{{ old('description') }}</textarea>
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h3 class="text-lg font-bold text-slate-900">Scope and team</h3>
            <fieldset class="mt-6">
                <legend class="text-sm font-semibold text-slate-700">Project Scope</legend>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach(['university_wide' => 'University-wide', 'selected_campuses' => 'Selected campuses'] as $value => $label)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 hover:border-emerald-400">
                            <input type="radio" name="scope" value="{{ $value }}" required @checked(old('scope', 'university_wide') === $value) class="mt-1 text-emerald-700 focus:ring-emerald-600">
                            <span><span class="block text-sm font-semibold text-slate-800">{{ $label }}</span><span class="mt-1 block text-xs leading-5 text-slate-500">{{ $value === 'university_wide' ? 'Available across every university campus.' : 'Choose one or more participating campuses.' }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div data-campus-selection class="mt-6 {{ old('scope', 'university_wide') === 'selected_campuses' ? '' : 'hidden' }}">
                <p class="text-sm font-semibold text-slate-700">Campuses</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($campuses as $campus)
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            <input type="checkbox" name="campus_ids[]" value="{{ $campus->id }}" @checked(in_array($campus->id, old('campus_ids', []))) class="rounded text-emerald-700 focus:ring-emerald-600">
                            <span class="font-medium text-slate-700">{{ $campus->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="mt-7">
                <p class="text-sm font-semibold text-slate-700">Team Members</p>
                <p class="mt-1 text-xs text-slate-500">The selected project owner will always be included automatically.</p>
                <div class="mt-3 max-h-80 overflow-y-auto rounded-xl border border-slate-200">
                    @forelse($activeUsers as $activeUser)
                        <label class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50">
                            <input type="checkbox" name="member_ids[]" value="{{ $activeUser->id }}" @checked(in_array($activeUser->id, old('member_ids', []))) class="rounded text-emerald-700 focus:ring-emerald-600">
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-800">{{ $activeUser->name }}</span><span class="block truncate text-xs text-slate-500">{{ $activeUser->getRoleNames()->join(', ') ?: 'User' }} · {{ $activeUser->staffProfile?->campus?->name ?? 'University-wide' }}</span></span>
                        </label>
                    @empty
                        <p class="px-4 py-6 text-sm text-slate-500">No active users are available.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h3 class="text-lg font-bold text-slate-900">Schedule and configuration</h3>
            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                <label class="block text-sm font-semibold text-slate-700">Start Date<input name="start_date" type="date" required value="{{ old('start_date') }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal"></label>
                <label class="block text-sm font-semibold text-slate-700">Due Date<input name="due_date" type="date" required value="{{ old('due_date') }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal"></label>
                <label class="block text-sm font-semibold text-slate-700">Priority
                    <select name="priority_level" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal">@foreach(App\Models\Project::PRIORITIES as $priority)<option value="{{ $priority }}" @selected(old('priority_level', 'medium') === $priority)>{{ App\Models\Project::label($priority) }}</option>@endforeach</select>
                </label>
                <label class="block text-sm font-semibold text-slate-700">Progress Method
                    <select name="progress_method" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal">@foreach(App\Models\Project::PROGRESS_METHODS as $method)<option value="{{ $method }}" @selected(old('progress_method', 'tasks') === $method)>{{ App\Models\Project::label($method) }}</option>@endforeach</select>
                </label>
                <label class="block text-sm font-semibold text-slate-700 md:col-span-2">Objectives<textarea name="objectives" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal">{{ old('objectives') }}</textarea></label>
                <label class="block text-sm font-semibold text-slate-700 md:col-span-2">Expected Deliverables<textarea name="expected_deliverables" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal">{{ old('expected_deliverables') }}</textarea></label>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><a href="{{ route('projects.index') }}" class="rounded-xl border border-slate-300 px-6 py-3 text-center font-semibold text-slate-700 hover:bg-white">Cancel</a><button type="submit" class="rounded-xl bg-emerald-800 px-6 py-3 font-semibold text-white hover:bg-emerald-900">Create Project</button></div>
    </form>
</div>

<script>
    (() => {
        const scopeInputs = document.querySelectorAll('input[name="scope"]');
        const campusSelection = document.querySelector('[data-campus-selection]');
        const updateScope = () => campusSelection?.classList.toggle('hidden', document.querySelector('input[name="scope"]:checked')?.value !== 'selected_campuses');
        scopeInputs.forEach((input) => input.addEventListener('change', updateScope));
        updateScope();
    })();
</script>
@endsection

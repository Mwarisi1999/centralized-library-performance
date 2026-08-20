@extends('layouts.app')

@section('title', 'Create Task')
@section('section-label', 'Work Management')
@section('page-title', 'Create Task')

@section('content')
@php
    $selectedProjectId = (int) old('project_id', $preselectedProjectId);
    $selectedAssigneeIds = collect(old('assignee_ids', []))->map(fn ($id) => (int) $id)->all();
@endphp
<div class="mx-auto max-w-6xl">
    <header class="mb-8"><a href="{{ route('tasks.index') }}" class="inline-flex text-sm font-semibold text-emerald-700 hover:text-emerald-900">&larr; Back to Tasks</a><h2 class="mt-5 text-3xl font-bold tracking-tight text-slate-900">Create Task</h2><p class="mt-2 text-slate-600">Create a focused piece of project work and assign it to active project members.</p></header>

    @if($errors->any())<div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-700" role="alert"><p class="font-semibold">Please correct the following:</p><ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('tasks.store') }}" class="space-y-6">
        @csrf
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h3 class="text-lg font-bold text-slate-900">Task information</h3>
            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                <label class="block text-sm font-semibold text-slate-700 md:col-span-2">Project
                    <select name="project_id" required data-project-select class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal"><option value="">Select project</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected($selectedProjectId === $project->id)>{{ $project->project_code }} — {{ $project->title }}</option>@endforeach</select>
                </label>
                <label class="block text-sm font-semibold text-slate-700 md:col-span-2">Task Title<input name="title" type="text" required value="{{ old('title') }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal focus:border-emerald-600 focus:ring-emerald-600"></label>
                <label class="block text-sm font-semibold text-slate-700 md:col-span-2">Description<textarea name="description" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal">{{ old('description') }}</textarea></label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h3 class="text-lg font-bold text-slate-900">Assignees</h3><p class="mt-1 text-sm text-slate-500">Only active members of the selected project can be assigned.</p>
            <div class="mt-5 rounded-xl border border-slate-200" data-assignee-placeholder><p class="px-5 py-8 text-center text-sm text-slate-500">Select a project to see its available members.</p></div>
            @foreach($projects as $project)
                <div data-project-assignees="{{ $project->id }}" class="mt-5 hidden max-h-80 overflow-y-auto rounded-xl border border-slate-200">
                    @forelse($project->projectMembers as $membership)
                        @php($member = $membership->user)
                        @if($member)
                            <label class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50"><input type="checkbox" name="assignee_ids[]" value="{{ $member->id }}" @checked(in_array($member->id, $selectedAssigneeIds, true)) disabled class="rounded text-emerald-700 focus:ring-emerald-600"><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-800">{{ $member->name }}</span><span class="block truncate text-xs text-slate-500">{{ $member->getRoleNames()->join(', ') ?: 'User' }} · {{ $member->staffProfile?->campus?->name ?? 'University-wide' }}</span></span></label>
                        @endif
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-slate-500">This project has no eligible active members.</p>
                    @endforelse
                </div>
            @endforeach
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h3 class="text-lg font-bold text-slate-900">Schedule and progress</h3>
            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                <label class="block text-sm font-semibold text-slate-700">Start Date<input name="start_date" type="date" value="{{ old('start_date') }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal"></label>
                <label class="block text-sm font-semibold text-slate-700">Due Date<input name="due_date" type="date" value="{{ old('due_date') }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal"></label>
                <label class="block text-sm font-semibold text-slate-700">Priority<select name="priority" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal">@foreach(App\Models\Task::PRIORITIES as $priority)<option value="{{ $priority }}" @selected(old('priority','medium') === $priority)>{{ App\Models\Task::label($priority) }}</option>@endforeach</select></label>
                <label class="block text-sm font-semibold text-slate-700">Status<select name="status" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal">@foreach(App\Models\Task::STATUSES as $status)<option value="{{ $status }}" @selected(old('status','not_started') === $status)>{{ App\Models\Task::label($status) }}</option>@endforeach</select></label>
                <label class="block text-sm font-semibold text-slate-700">Progress Percentage<input name="progress_percentage" type="number" min="0" max="100" required value="{{ old('progress_percentage', 0) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal"></label>
                <label class="block text-sm font-semibold text-slate-700">Estimated Hours<input name="estimated_hours" type="number" min="0" step="0.25" value="{{ old('estimated_hours') }}" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal"></label>
                <label class="block text-sm font-semibold text-slate-700 md:col-span-2">Remarks<textarea name="remarks" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-normal">{{ old('remarks') }}</textarea></label>
            </div>
        </section>
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><a href="{{ route('tasks.index') }}" class="rounded-xl border border-slate-300 px-6 py-3 text-center font-semibold text-slate-700 hover:bg-white">Cancel</a><button type="submit" class="rounded-xl bg-emerald-800 px-6 py-3 font-semibold text-white hover:bg-emerald-900">Create Task</button></div>
    </form>
</div>

<script>
    (() => {
        const projectSelect = document.querySelector('[data-project-select]');
        const groups = document.querySelectorAll('[data-project-assignees]');
        const placeholder = document.querySelector('[data-assignee-placeholder]');
        const updateAssignees = () => {
            const selected = projectSelect?.value;
            let hasGroup = false;
            groups.forEach((group) => {
                const active = group.dataset.projectAssignees === selected;
                group.classList.toggle('hidden', !active);
                group.querySelectorAll('input').forEach((input) => input.disabled = !active);
                if (active) hasGroup = true;
            });
            placeholder?.classList.toggle('hidden', hasGroup);
        };
        projectSelect?.addEventListener('change', updateAssignees);
        updateAssignees();
    })();
</script>
@endsection

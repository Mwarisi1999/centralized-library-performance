@extends('layouts.app')

@section('title', $project->project_code.' - '.$project->title)
@section('section-label', 'Performance Management')
@section('page-title', 'Project Detail')

@section('content')
@php
    $scopeLabel = $project->scope === 'university_wide'
        ? 'University-wide'
        : $project->campuses->pluck('name')->join(', ');
    $statusClasses = match($project->status) {
        'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'in_progress' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'on_hold' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'cancelled' => 'bg-red-50 text-red-700 ring-red-600/20',
        default => 'bg-slate-100 text-slate-700 ring-slate-500/20',
    };
@endphp

<div class="mx-auto max-w-screen-2xl">
    <header class="mb-8">
        <a href="{{ route('projects.index') }}" class="inline-flex text-sm font-semibold text-emerald-700 hover:text-emerald-900">&larr; Back to Projects</a>
        <div class="mt-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="font-mono text-sm font-bold text-emerald-700">{{ $project->project_code }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ $project->title }}</h2>
                <p class="mt-3 max-w-4xl leading-7 text-slate-600">{{ $project->description ?: 'No project description has been provided.' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3 self-start">
                <span class="inline-flex rounded-full px-3 py-1.5 text-sm font-semibold ring-1 ring-inset {{ $statusClasses }}">{{ App\Models\Project::label($project->status) }}</span>
                @can('update', $project)<a href="{{ route('projects.edit', $project) }}" class="inline-flex rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-900">Edit Project</a>@endcan
            </div>
        </div>
    </header>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 xl:col-span-2">
            <h3 class="text-lg font-bold text-slate-900">Project overview</h3>
            <dl class="mt-6 grid gap-x-8 gap-y-6 sm:grid-cols-2">
                @foreach([
                    ['Category', $project->category?->name ?? 'Uncategorised'],
                    ['Campus Scope', $scopeLabel],
                    ['Owner', $project->owner->name],
                    ['Creator', $project->creator->name],
                    ['Start Date', $project->start_date->format('d F Y')],
                    ['Due Date', $project->due_date->format('d F Y')],
                    ['Priority', App\Models\Project::label($project->priority_level)],
                    ['Progress Method', $project->progress_method === 'tasks' ? 'Automatic — calculated from active tasks' : App\Models\Project::label($project->progress_method)],
                ] as [$label, $value])
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt><dd class="mt-2 font-semibold text-slate-800">{{ $value }}</dd></div>
                @endforeach
            </dl>

            <div class="mt-8 border-t border-slate-200 pt-7">
                <div class="flex items-center justify-between"><div><h4 class="font-bold text-slate-900">Progress</h4><p class="mt-1 text-xs font-semibold text-slate-500">{{ $project->progress_method === 'tasks' ? 'Automatic — calculated from active tasks' : 'Manual' }}</p></div><span class="text-sm font-bold text-emerald-700">{{ number_format((float) $project->progress_percentage, 2) }}%</span></div>
                <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-emerald-600" style="width: {{ $project->progress_percentage }}%"></div></div>
            </div>
        </section>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-slate-900">Objectives</h3>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $project->objectives ?: 'No objectives have been recorded.' }}</p>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-slate-900">Expected Deliverables</h3>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $project->expected_deliverables ?: 'No expected deliverables have been recorded.' }}</p>
            </section>
        </div>
    </div>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5"><h3 class="text-lg font-bold text-slate-900">Project Team</h3><p class="mt-1 text-sm text-slate-500">{{ $project->projectMembers->count() }} active {{ Str::plural('member', $project->projectMembers->count()) }}</p></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr>@foreach(['Name', 'Role', 'Position', 'Campus', 'Library'] as $heading)<th class="px-6 py-3.5">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($project->projectMembers->sortBy('user.name') as $membership)
                        @php($member = $membership->user)
                        @if($member)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4 font-semibold text-slate-800">
                                    @can('view staff')<a href="{{ route('admin.staff.show', $member) }}" class="text-emerald-700 hover:text-emerald-900">{{ $member->name }}</a>@else{{ $member->name }}@endcan
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600">{{ $membership->project_role ?: ($member->getRoleNames()->join(', ') ?: 'Member') }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600">{{ $member->staffProfile?->position?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600">{{ $member->staffProfile?->campus?->name ?? 'University-wide' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600">{{ $member->staffProfile?->library?->name ?? 'University-wide' }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">No active project members.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div><h3 class="text-lg font-bold text-slate-900">Project Tasks</h3><p class="mt-1 text-sm text-slate-500">Visible work items for this project.</p></div>
            @can('create', App\Models\Task::class)<a href="{{ route('tasks.create', ['project' => $project->id]) }}" class="inline-flex items-center justify-center rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">+ Add Task</a>@endcan
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr>@foreach(['Task Code','Title','Assignees','Due Date','Priority','Status','Progress'] as $heading)<th class="whitespace-nowrap px-6 py-3.5">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($projectTasks as $task)
                        <tr><td class="whitespace-nowrap px-6 py-4"><a href="{{ route('tasks.show',$task) }}" class="font-mono text-xs font-bold text-emerald-700 hover:text-emerald-900">{{ $task->task_code }}</a></td><td class="min-w-56 px-6 py-4 font-semibold text-slate-800">{{ $task->title }}</td><td class="min-w-48 px-6 py-4 text-slate-600">{{ $task->assignees->where('pivot.is_active',true)->pluck('name')->join(', ') ?: 'Unassigned' }}</td><td class="whitespace-nowrap px-6 py-4 text-slate-600">{{ $task->due_date?->format('d M Y') ?? '—' }}</td><td class="whitespace-nowrap px-6 py-4 font-semibold capitalize text-slate-700">{{ $task->priority }}</td><td class="whitespace-nowrap px-6 py-4 text-slate-600">{{ App\Models\Task::label($task->status) }}</td><td class="min-w-28 px-6 py-4"><div class="flex items-center gap-2"><div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-emerald-600" style="width: {{ $task->progress_percentage }}%"></div></div><span class="text-xs font-semibold">{{ number_format((float) $task->progress_percentage, 2) }}%</span></div></td></tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-slate-500">No visible tasks have been added to this project.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

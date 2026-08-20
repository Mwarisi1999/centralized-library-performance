@extends('layouts.app')

@section('title', 'Projects')
@section('section-label', 'Performance Management')
@section('page-title', 'Projects')

@section('content')
<div class="mx-auto max-w-screen-2xl">
    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-3xl font-bold tracking-tight text-slate-900">Projects</h2>
            <p class="mt-2 max-w-3xl text-slate-600">Manage and monitor university library projects across campuses.</p>
        </div>
        @can('create', App\Models\Project::class)
            <a href="{{ route('projects.create') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white shadow-sm hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">+ Create Project</a>
        @endcan
    </div>

    <section aria-label="Project summary" class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        @foreach([
            ['Total Projects', $summary['total']],
            ['Planned', $summary['planned']],
            ['In Progress', $summary['in_progress']],
            ['Completed', $summary['completed']],
            ['On Hold', $summary['on_hold']],
        ] as [$label, $value])
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" action="{{ route('projects.index') }}" class="border-b border-slate-200 p-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="md:col-span-2 xl:col-span-6">
                    <label for="search" class="sr-only">Search projects</label>
                    <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Search by project code, title or description..." class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                </div>

                <select name="status" aria-label="Status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">All statuses</option>
                    @foreach(App\Models\Project::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ App\Models\Project::label($status) }}</option>
                    @endforeach
                </select>
                <select name="priority" aria-label="Priority" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">All priorities</option>
                    @foreach(App\Models\Project::PRIORITIES as $priority)
                        <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ App\Models\Project::label($priority) }}</option>
                    @endforeach
                </select>
                <select name="category_id" aria-label="Category" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="campus_id" aria-label="Campus" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">All campuses</option>
                    @foreach($campuses as $campus)
                        <option value="{{ $campus->id }}" @selected((string) request('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>
                    @endforeach
                </select>
                <select name="owner_id" aria-label="Owner" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">All owners</option>
                    @foreach($owners as $owner)
                        <option value="{{ $owner->id }}" @selected((string) request('owner_id') === (string) $owner->id)>{{ $owner->name }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button class="flex-1 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    @if(request()->hasAny(['search', 'status', 'priority', 'category_id', 'campus_id', 'owner_id']))
                        <a href="{{ route('projects.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        @foreach(['Project Code', 'Title', 'Category', 'Campus Scope', 'Owner', 'Start Date', 'Due Date', 'Priority', 'Status', 'Progress', 'Actions'] as $heading)
                            <th class="whitespace-nowrap px-5 py-3.5">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($projects as $project)
                        @php
                            $statusClasses = match($project->status) {
                                'completed' => 'bg-emerald-50 text-emerald-700',
                                'in_progress' => 'bg-blue-50 text-blue-700',
                                'on_hold' => 'bg-amber-50 text-amber-700',
                                'cancelled' => 'bg-red-50 text-red-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                            $scopeLabel = $project->scope === 'university_wide'
                                ? 'University-wide'
                                : ($project->campuses->count() === 1 ? $project->campuses->first()->name : $project->campuses->count().' Campuses');
                        @endphp
                        <tr class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs font-bold text-slate-700">{{ $project->project_code }}</td>
                            <td class="min-w-64 px-5 py-4"><p class="font-semibold text-slate-900">{{ $project->title }}</p><p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ $project->description ?: 'No description' }}</p></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $project->category?->name ?? 'Uncategorised' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $scopeLabel }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $project->owner->name }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $project->start_date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $project->due_date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap px-5 py-4"><span class="font-semibold capitalize text-slate-700">{{ $project->priority_level }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">{{ App\Models\Project::label($project->status) }}</span></td>
                            <td class="min-w-32 px-5 py-4">
                                <div class="flex items-center gap-2"><div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-emerald-600" style="width: {{ $project->progress_percentage }}%"></div></div><span class="text-xs font-semibold text-slate-600">{{ number_format((float) $project->progress_percentage, 2) }}%</span></div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('projects.show', $project) }}" class="font-semibold text-emerald-700 hover:text-emerald-900">View</a>
                                    @can('update', $project)<a href="{{ route('projects.edit', $project) }}" class="font-semibold text-slate-700 hover:text-slate-950">Edit</a>@endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-6 py-16 text-center"><p class="text-lg font-semibold text-slate-800">No projects found.</p><p class="mt-2 text-sm text-slate-500">Create a project or adjust the current search and filters.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($projects->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">{{ $projects->links() }}</div>
        @endif
    </section>
</div>
@endsection

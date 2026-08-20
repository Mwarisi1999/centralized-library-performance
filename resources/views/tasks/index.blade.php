@extends('layouts.app')

@section('title', 'Tasks')
@section('section-label', 'Work Management')
@section('page-title', 'Tasks')

@section('content')
<div class="mx-auto max-w-screen-2xl">
    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div><h2 class="text-3xl font-bold tracking-tight text-slate-900">Tasks</h2><p class="mt-2 max-w-3xl text-slate-600">Track assigned work, deadlines and progress across library projects.</p></div>
        @can('create', App\Models\Task::class)<a href="{{ route('tasks.create') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white shadow-sm hover:bg-emerald-900">+ Create Task</a>@endcan
    </div>

    <section aria-label="Task summary" class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach([
            ['Total Tasks', $summary['total']], ['Not Started', $summary['not_started']],
            ['In Progress', $summary['in_progress']], ['Pending Review', $summary['pending_review']],
            ['Completed', $summary['completed']], ['Overdue', $summary['overdue']],
        ] as [$label, $value])
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($value) }}</p></div>
        @endforeach
    </section>

    @if($reviewQueue->isNotEmpty())
    <section class="mb-6 rounded-2xl border border-violet-200 bg-white shadow-sm">
        <div class="border-b border-violet-100 px-6 py-5"><h3 class="text-lg font-bold text-slate-900">Tasks Awaiting My Review</h3><p class="mt-1 text-sm text-slate-500">Operational work submitted directly to you as the recorded supervisor.</p></div>
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-violet-50 text-xs font-semibold uppercase text-violet-700"><tr>@foreach(['Task','Project','Assignees','Submitted','Progress','Action'] as $heading)<th class="px-5 py-3">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">@foreach($reviewQueue as $reviewTask)<tr><td class="px-5 py-4"><p class="font-mono text-xs font-bold text-violet-700">{{ $reviewTask->task_code }}</p><p class="mt-1 font-semibold">{{ $reviewTask->title }}</p></td><td class="px-5 py-4">{{ $reviewTask->project->project_code }} — {{ $reviewTask->project->title }}</td><td class="px-5 py-4">{{ $reviewTask->assignees->where('pivot.is_active',true)->pluck('name')->join(', ') }}</td><td class="whitespace-nowrap px-5 py-4">{{ $reviewTask->submitted_at?->format('d M Y, h:i A') ?? '—' }}</td><td class="px-5 py-4 font-semibold">{{ number_format((float)$reviewTask->progress_percentage,2) }}%</td><td class="px-5 py-4"><a href="{{ route('tasks.show',$reviewTask) }}" class="font-semibold text-violet-700">View / Review</a></td></tr>@endforeach</tbody></table></div>
    </section>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" action="{{ route('tasks.index') }}" class="border-b border-slate-200 p-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="md:col-span-2 xl:col-span-6"><label for="task-search" class="sr-only">Search tasks</label><input id="task-search" name="search" type="search" value="{{ request('search') }}" placeholder="Search by task code, title or description..." class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-emerald-600 focus:ring-emerald-600"></div>
                <select name="project_id" aria-label="Project" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option value="">All projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>{{ $project->project_code }} — {{ $project->title }}</option>@endforeach</select>
                <select name="status" aria-label="Status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option value="">All statuses</option>@foreach(App\Models\Task::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ App\Models\Task::label($status) }}</option>@endforeach</select>
                <select name="priority" aria-label="Priority" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option value="">All priorities</option>@foreach(App\Models\Task::PRIORITIES as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ App\Models\Task::label($priority) }}</option>@endforeach</select>
                <select name="assignee_id" aria-label="Assignee" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option value="">All assignees</option>@foreach($assignees as $assignee)<option value="{{ $assignee->id }}" @selected((string) request('assignee_id') === (string) $assignee->id)>{{ $assignee->name }}</option>@endforeach</select>
                <select name="campus_id" aria-label="Campus" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"><option value="">All campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) request('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>@endforeach</select>
                <div class="flex gap-2"><button class="flex-1 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>@if(request()->hasAny(['search','project_id','status','priority','assignee_id','campus_id']))<a href="{{ route('tasks.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>@endif</div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr>@foreach(['Task Code','Task','Project','Assignees','Start Date','Due Date','Priority','Status','Progress','Actions'] as $heading)<th class="whitespace-nowrap px-5 py-3.5">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tasks as $task)
                        @php($statusClasses = match($task->status) {'completed'=>'bg-emerald-50 text-emerald-700','in_progress'=>'bg-blue-50 text-blue-700','pending_review'=>'bg-violet-50 text-violet-700','deferred'=>'bg-amber-50 text-amber-700','cancelled'=>'bg-red-50 text-red-700',default=>'bg-slate-100 text-slate-700'})
                        <tr class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs font-bold text-slate-700">{{ $task->task_code }}</td>
                            <td class="min-w-64 px-5 py-4"><p class="font-semibold text-slate-900">{{ $task->title }}</p><p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ $task->description ?: 'No description' }}</p></td>
                            <td class="min-w-56 px-5 py-4"><a href="{{ route('projects.show', $task->project) }}" class="font-semibold text-emerald-700 hover:text-emerald-900">{{ $task->project->project_code }}</a><p class="mt-1 text-xs text-slate-500">{{ $task->project->title }}</p></td>
                            <td class="min-w-48 px-5 py-4 text-slate-600">{{ $task->assignees->where('pivot.is_active', true)->pluck('name')->join(', ') ?: 'Unassigned' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $task->start_date?->format('d M Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 {{ $task->is_overdue ? 'font-semibold text-red-700' : 'text-slate-600' }}">{{ $task->due_date?->format('d M Y') ?? '—' }}@if($task->is_overdue)<span class="ml-1 text-xs">Overdue</span>@endif</td>
                            <td class="whitespace-nowrap px-5 py-4 font-semibold capitalize text-slate-700">{{ $task->priority }}</td>
                            <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">{{ App\Models\Task::label($task->status) }}</span></td>
                            <td class="min-w-32 px-5 py-4"><div class="flex items-center gap-2"><div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-emerald-600" style="width: {{ $task->progress_percentage }}%"></div></div><span class="text-xs font-semibold text-slate-600">{{ $task->progress_percentage }}%</span></div></td>
                            <td class="px-5 py-4"><a href="{{ route('tasks.show', $task) }}" class="font-semibold text-emerald-700 hover:text-emerald-900">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-6 py-16 text-center"><p class="text-lg font-semibold text-slate-800">No tasks found.</p><p class="mt-2 text-sm text-slate-500">Create a task or adjust the current search and filters.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tasks->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $tasks->links() }}</div>@endif
    </section>
</div>
@endsection

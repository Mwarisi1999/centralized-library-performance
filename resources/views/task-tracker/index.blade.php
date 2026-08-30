@extends('layouts.app')
@section('title', 'Task Tracker')
@section('section-label', 'Work Planning')
@section('page-title', 'Task Tracker')

@section('content')
<div class="mx-auto max-w-screen-2xl">
    <header class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><h2 class="text-3xl font-bold">Task Tracker</h2><p class="mt-2 text-slate-600">Track your assigned tasks, deadlines, progress, subtasks, and recorded hours.</p></div>
        <div class="flex gap-3"><a href="{{ route('daily-activities.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-700">Daily Activities</a>@can('create', App\Models\Task::class)<a href="{{ route('tasks.create') }}" class="rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white">+ Add Task</a>@endcan</div>
    </header>
    <section class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        @foreach([['Assigned Tasks',$summary['total']],['Completed',$summary['completed']],['In Progress',$summary['in_progress']],['Overdue',$summary['overdue']],['Average Progress',$summary['average_progress'].'%']] as [$label,$value])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold">{{ $value }}</p></article>
        @endforeach
    </section>
    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" class="grid gap-4 md:grid-cols-4 md:items-end">
            <label class="text-sm font-semibold md:col-span-2">Search<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Task code, title, or project" class="mt-2 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Status<select name="status" class="mt-2 w-full rounded-xl border-slate-300"><option value="">All statuses</option>@foreach(App\Models\Task::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '')===$status)>{{ App\Models\Task::label($status) }}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">Priority<select name="priority" class="mt-2 w-full rounded-xl border-slate-300"><option value="">All priorities</option>@foreach(App\Models\Task::PRIORITIES as $priority)<option value="{{ $priority }}" @selected(($filters['priority'] ?? '')===$priority)>{{ ucfirst($priority) }}</option>@endforeach</select></label>
            <div class="flex gap-3 md:col-span-4 md:justify-end"><a href="{{ route('task-tracker.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 font-semibold">Reset</a><button class="rounded-xl bg-slate-900 px-5 py-2.5 font-semibold text-white">Apply Filters</button></div>
        </form>
    </section>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500"><tr>@foreach(['Task','Project','Assigned','Due','Priority','Status','Progress','Hours','Action'] as $heading)<th class="whitespace-nowrap px-5 py-3.5">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($tasks as $task)
                <tr><td class="min-w-64 px-5 py-4"><p class="font-mono text-xs font-bold text-blue-700">{{ $task->task_code }}</p><p class="mt-1 font-semibold">{{ $task->title }}</p></td><td class="min-w-52 px-5 py-4">{{ $task->project->title }}</td><td class="whitespace-nowrap px-5 py-4">{{ $task->start_date?->format('d M Y') ?? '—' }}</td><td class="whitespace-nowrap px-5 py-4 {{ $task->is_overdue ? 'font-semibold text-red-700' : '' }}">{{ $task->due_date?->format('d M Y') ?? '—' }}</td><td class="px-5 py-4 capitalize">{{ $task->priority }}</td><td class="px-5 py-4"><span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ App\Models\Task::label($task->status) }}</span></td><td class="min-w-44 px-5 py-4"><div class="flex justify-between text-xs"><span>{{ number_format((float)$task->progress_percentage,1) }}%</span><span>{{ $task->subtasks->count() }} subtasks</span></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-blue-600" style="width: {{ min(100,(float)$task->progress_percentage) }}%"></div></div></td><td class="whitespace-nowrap px-5 py-4">{{ App\Models\WorkEntry::formatMinutes((int)($task->logged_minutes ?? 0)) }}</td><td class="px-5 py-4"><a href="{{ route('tasks.show',$task) }}" class="font-semibold text-blue-700">Open</a></td></tr>
            @empty<tr><td colspan="9" class="px-6 py-14 text-center text-slate-500">No assigned tasks match these filters.</td></tr>@endforelse
            </tbody>
        </table></div>
        @if($tasks->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $tasks->links() }}</div>@endif
    </section>
</div>
@endsection

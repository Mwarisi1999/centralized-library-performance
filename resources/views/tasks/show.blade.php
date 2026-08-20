@extends('layouts.app')

@section('title', $task->task_code.' - '.$task->title)
@section('section-label', 'Work Management')
@section('page-title', 'Task Detail')

@section('content')
@php($statusClasses = match($task->status) {'completed'=>'bg-emerald-50 text-emerald-700 ring-emerald-600/20','in_progress'=>'bg-blue-50 text-blue-700 ring-blue-600/20','pending_review'=>'bg-violet-50 text-violet-700 ring-violet-600/20','deferred'=>'bg-amber-50 text-amber-700 ring-amber-600/20','cancelled'=>'bg-red-50 text-red-700 ring-red-600/20',default=>'bg-slate-100 text-slate-700 ring-slate-500/20'})
<div class="mx-auto max-w-screen-xl">
    <header class="mb-8"><a href="{{ route('tasks.index') }}" class="inline-flex text-sm font-semibold text-emerald-700 hover:text-emerald-900">&larr; Back to Tasks</a><div class="mt-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><p class="font-mono text-sm font-bold text-emerald-700">{{ $task->task_code }}</p><h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ $task->title }}</h2><p class="mt-3 max-w-4xl leading-7 text-slate-600">{{ $task->description ?: 'No task description has been provided.' }}</p></div><span class="inline-flex self-start rounded-full px-3 py-1.5 text-sm font-semibold ring-1 ring-inset {{ $statusClasses }}">{{ App\Models\Task::label($task->status) }}</span></div></header>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 lg:col-span-2">
            <h3 class="text-lg font-bold text-slate-900">Task overview</h3>
            <dl class="mt-6 grid gap-x-8 gap-y-6 sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Project</dt><dd class="mt-2"><a href="{{ route('projects.show', $task->project) }}" class="font-semibold text-emerald-700 hover:text-emerald-900">{{ $task->project->project_code }} — {{ $task->project->title }}</a></dd></div>
                @foreach([['Created By',$task->creator->name],['Assigned By',$task->assignedBy?->name ?? '—'],['Start Date',$task->start_date?->format('d F Y') ?? '—'],['Due Date',$task->due_date?->format('d F Y') ?? '—'],['Priority',App\Models\Task::label($task->priority)],['Estimated Hours',$task->estimated_hours !== null ? rtrim(rtrim($task->estimated_hours, '0'), '.').' hours' : '—'],['Created At',$task->created_at->format('d F Y, H:i')],['Updated At',$task->updated_at->format('d F Y, H:i')]] as [$label,$value])<div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt><dd class="mt-2 font-semibold text-slate-800">{{ $value }}</dd></div>@endforeach
            </dl>
            <div class="mt-8 border-t border-slate-200 pt-7"><div class="flex items-center justify-between"><div><h4 class="font-bold text-slate-900">Progress</h4><p class="mt-1 text-xs font-semibold text-slate-500">{{ $task->hasAutomaticProgress() ? 'Automatic — calculated from active subtasks' : 'Manual' }}</p></div><span class="text-sm font-bold text-emerald-700">{{ number_format((float)$task->progress_percentage, 2) }}%</span></div><div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-emerald-600" style="width: {{ $task->progress_percentage }}%"></div></div></div>
        </section>
        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-slate-900">Assignees</h3>
                <div class="mt-4 space-y-3">
                    @forelse($task->taskAssignees as $assignment)
                        @php($assignee = $assignment->user)
                        @if($assignee)
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <p class="font-semibold text-slate-800">
                                    @can('view staff')
                                        <a href="{{ route('admin.staff.show', $assignee) }}" class="text-emerald-700 hover:text-emerald-900">{{ $assignee->name }}</a>
                                    @else
                                        {{ $assignee->name }}
                                    @endcan
                                </p>
                                <p class="mt-1 text-xs text-slate-500">{{ $assignee->getRoleNames()->join(', ') ?: 'User' }} · {{ $assignee->staffProfile?->campus?->name ?? 'University-wide' }}</p>
                            </div>
                        @endif
                    @empty
                        <p class="text-sm text-slate-500">No active assignees.</p>
                    @endforelse
                </div>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h3 class="text-lg font-bold text-slate-900">Remarks</h3><p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $task->remarks ?: 'No remarks have been recorded.' }}</p></section>
        </div>
    </div>

    @if($task->status === 'pending_review')
    <section class="mt-6 rounded-2xl border border-violet-200 bg-violet-50 p-6"><h3 class="font-bold text-violet-900">Submitted for Review</h3><p class="mt-2 text-sm text-violet-800">Awaiting review by <strong>{{ $task->reviewer?->name ?? 'the configured supervisor' }}</strong>. Submitted {{ $task->submitted_at?->format('d M Y, h:i A') ?? '—' }} by {{ $task->submittedBy?->name ?? '—' }}.</p></section>
    @elseif($task->status === 'in_progress' && $task->reviews->where('status','returned')->isNotEmpty())
        @php($latestReturn = $task->reviews->where('status','returned')->sortByDesc('reviewed_at')->first())
        <section class="mt-6 rounded-2xl border border-amber-300 bg-amber-50 p-6"><h3 class="font-bold text-amber-900">Returned for Correction</h3><p class="mt-2 text-sm text-amber-800">{{ $latestReturn->remark }}</p><p class="mt-2 text-xs text-amber-700">Returned by {{ $latestReturn->reviewedBy?->name ?? $latestReturn->reviewer?->name }} on {{ $latestReturn->reviewed_at?->format('d M Y, h:i A') }}. Current progress has been preserved.</p></section>
    @elseif($task->status === 'completed')
    <section class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-6"><h3 class="font-bold text-emerald-900">Task Approved</h3><p class="mt-2 text-sm text-emerald-800">Approved by <strong>{{ $task->reviewedBy?->name ?? $task->reviewer?->name ?? 'Supervisor' }}</strong> on {{ $task->reviewed_at?->format('d M Y, h:i A') ?? $task->completed_at?->format('d M Y, h:i A') }}.</p></section>
    @endif

    @can('review',$task)
    @php($latestWorkRemark = $task->activities->whereNotNull('message')->sortByDesc('created_at')->first())
    <section class="mt-6 rounded-2xl border border-violet-200 bg-white p-6 shadow-sm sm:p-8"><h3 class="text-lg font-bold text-slate-900">Supervisor Review</h3><dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2"><div><dt class="font-semibold text-slate-500">Submitted By</dt><dd class="mt-1 font-bold">{{ $task->submittedBy?->name ?? '—' }}</dd></div><div><dt class="font-semibold text-slate-500">Submitted At</dt><dd class="mt-1 font-bold">{{ $task->submitted_at?->format('d M Y, h:i A') ?? '—' }}</dd></div><div><dt class="font-semibold text-slate-500">Reviewer</dt><dd class="mt-1 font-bold">{{ $task->reviewer?->name }}</dd></div><div><dt class="font-semibold text-slate-500">Task Progress</dt><dd class="mt-1 font-bold">{{ number_format((float)$task->progress_percentage,2) }}%</dd></div><div class="sm:col-span-2"><dt class="font-semibold text-slate-500">Assignees</dt><dd class="mt-1 font-bold">{{ $task->taskAssignees->pluck('user.name')->filter()->join(', ') }}</dd></div>@if($latestWorkRemark)<div class="sm:col-span-2"><dt class="font-semibold text-slate-500">Latest Relevant Remark</dt><dd class="mt-1 text-slate-700">{{ $latestWorkRemark->message }}</dd></div>@endif</dl>
      <div class="mt-6 grid gap-5 md:grid-cols-2"><form method="POST" action="{{ route('tasks.approve',$task) }}" class="rounded-xl border border-emerald-200 p-4">@csrf<label class="text-sm font-semibold">Approval Remark (optional)<textarea name="remark" rows="3" class="mt-2 w-full rounded-xl border-slate-300"></textarea></label><button class="mt-3 w-full rounded-xl bg-emerald-700 px-5 py-3 font-semibold text-white">Approve Task</button></form><form method="POST" action="{{ route('tasks.return-correction',$task) }}" class="rounded-xl border border-amber-200 p-4">@csrf<label class="text-sm font-semibold">Correction Reason (required)<textarea name="remark" required rows="3" class="mt-2 w-full rounded-xl border-slate-300"></textarea></label><button class="mt-3 w-full rounded-xl bg-amber-700 px-5 py-3 font-semibold text-white">Return for Correction</button></form></div>
    </section>
    @endcan

    @can('execute',$task)
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"><h3 class="text-lg font-bold">Task Actions</h3>
      @if($task->status==='not_started')<form method="POST" action="{{ route('tasks.start',$task) }}" class="mt-5">@csrf<button class="rounded-xl bg-blue-700 px-5 py-3 font-semibold text-white">Start Task</button></form>@endif
      @if($task->status==='in_progress')
        @if($task->hasAutomaticProgress())<p class="mt-4 rounded-xl bg-blue-50 p-4 text-sm font-semibold text-blue-800">Progress is calculated automatically from subtasks.</p>
        @else<form method="POST" action="{{ route('tasks.progress',$task) }}" class="mt-5 grid gap-3 sm:grid-cols-[160px_1fr_auto]">@csrf<input type="number" step="0.01" min="0" max="100" name="progress_percentage" value="{{ $task->progress_percentage }}" class="rounded-xl border-slate-300"><input name="message" placeholder="Optional work remark" class="rounded-xl border-slate-300"><button class="rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white">Update Progress</button></form>@endif
        @if((float)$task->progress_percentage===100.0)<form method="POST" action="{{ route('tasks.submit-review',$task) }}" class="mt-4">@csrf<button class="rounded-xl bg-violet-700 px-5 py-3 font-semibold text-white">Submit for Review</button></form>@endif
      @endif
    </section>
    @endcan

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"><div class="flex items-center justify-between"><div><h3 class="text-lg font-bold">Subtasks</h3><p class="mt-1 text-sm text-slate-500">Cancelled and inactive subtasks do not affect parent progress.</p></div>@can('addSubtask',$task)<a href="{{ route('tasks.subtasks.create',$task) }}" class="rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white">+ Add Subtask</a>@endcan</div>
      <div class="mt-5 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-b text-xs uppercase text-slate-500"><tr>@foreach(['Code','Title','Assignee','Due Date','Priority','Status','Progress'] as $heading)<th class="px-3 py-3">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y">@forelse($task->subtasks as $subtask)<tr><td class="px-3 py-4 font-mono"><a class="font-bold text-emerald-700" href="{{ route('subtasks.show',$subtask) }}">{{ $subtask->subtask_code }}</a></td><td class="px-3 py-4 font-semibold">{{ $subtask->title }}</td><td class="px-3 py-4">{{ $subtask->assignee?->name ?? '—' }}</td><td class="px-3 py-4">{{ $subtask->due_date?->format('d M Y') ?? '—' }}</td><td class="px-3 py-4">{{ App\Models\Subtask::label($subtask->priority) }}</td><td class="px-3 py-4">{{ App\Models\Subtask::label($subtask->status) }}</td><td class="min-w-32 px-3 py-4"><div class="flex justify-between text-xs"><span>{{ number_format((float)$subtask->progress_percentage,2) }}%</span></div><div class="mt-1 h-2 rounded bg-slate-200"><div class="h-2 rounded bg-emerald-600" style="width:{{ $subtask->progress_percentage }}%"></div></div></td></tr>@empty<tr><td colspan="7" class="px-3 py-8 text-center text-slate-500">No subtasks have been added.</td></tr>@endforelse</tbody></table></div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"><h3 class="text-lg font-bold">Activity History</h3><div class="mt-5 space-y-4">@forelse($task->activities->sortByDesc('created_at') as $activity)<article class="border-l-2 border-emerald-600 pl-4"><div class="flex flex-wrap justify-between gap-2"><p class="font-semibold">{{ $activity->user?->name ?? 'System' }}</p><time class="text-xs text-slate-500">{{ $activity->created_at->format('d M Y, h:i A') }}</time></div><p class="mt-1 text-sm text-slate-700">{{ App\Models\Task::label($activity->activity_type) }}@if($activity->old_progress!==null && $activity->new_progress!==null) — {{ number_format((float)$activity->old_progress,2) }}% to {{ number_format((float)$activity->new_progress,2) }}%@endif</p>@if($activity->old_status && $activity->new_status && $activity->old_status!==$activity->new_status)<p class="mt-1 text-xs text-slate-500">{{ App\Models\Task::label($activity->old_status) }} → {{ App\Models\Task::label($activity->new_status) }}</p>@endif @if($activity->message)<p class="mt-2 text-sm text-slate-600">{{ $activity->message }}</p>@endif</article>@empty<p class="text-sm text-slate-500">No activity has been recorded.</p>@endforelse</div></section>
</div>
@endsection

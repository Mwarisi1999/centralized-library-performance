@extends('layouts.app')
@section('title','Add Subtask')
@section('section-label','Work Management')
@section('page-title','Add Subtask')
@section('content')
<div class="mx-auto max-w-4xl">
 <a href="{{ route('tasks.show',$task) }}" class="text-sm font-semibold text-emerald-700">&larr; Back to Task</a>
 <h2 class="mt-5 text-3xl font-bold text-slate-900">Add Subtask</h2><p class="mt-2 text-slate-600">Parent: <strong>{{ $task->task_code }} — {{ $task->title }}</strong></p>
 @if($errors->any())<div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 <form method="POST" action="{{ route('tasks.subtasks.store',$task) }}" class="mt-7 space-y-6">@csrf
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div class="grid gap-6 md:grid-cols-2">
   <label class="text-sm font-semibold md:col-span-2">Subtask Title<input name="title" required value="{{ old('title') }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
   <label class="text-sm font-semibold md:col-span-2">Description<textarea name="description" rows="4" class="mt-2 w-full rounded-xl border-slate-300">{{ old('description') }}</textarea></label>
   <label class="text-sm font-semibold">Assignee<select name="assigned_to" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Unassigned</option>@foreach($task->taskAssignees as $assignment)<option value="{{ $assignment->user_id }}" @selected((int)old('assigned_to')===$assignment->user_id)>{{ $assignment->user->name }}</option>@endforeach</select></label>
   <label class="text-sm font-semibold">Priority<select name="priority" class="mt-2 w-full rounded-xl border-slate-300">@foreach(App\Models\Subtask::PRIORITIES as $value)<option value="{{ $value }}" @selected(old('priority','medium')===$value)>{{ App\Models\Subtask::label($value) }}</option>@endforeach</select></label>
   <label class="text-sm font-semibold">Start Date<input type="date" name="start_date" value="{{ old('start_date') }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
   <label class="text-sm font-semibold">Due Date<input type="date" name="due_date" value="{{ old('due_date') }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
   <label class="text-sm font-semibold">Status<select name="status" class="mt-2 w-full rounded-xl border-slate-300">@foreach(App\Models\Subtask::STATUSES as $value)<option value="{{ $value }}" @selected(old('status','not_started')===$value)>{{ App\Models\Subtask::label($value) }}</option>@endforeach</select></label>
   <label class="text-sm font-semibold">Progress Percentage<input type="number" step="0.01" min="0" max="100" name="progress_percentage" value="{{ old('progress_percentage',0) }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
   <label class="text-sm font-semibold">Estimated Hours<input type="number" step="0.25" min="0" name="estimated_hours" value="{{ old('estimated_hours') }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
  </div></section>
  <div class="flex justify-end gap-3"><a href="{{ route('tasks.show',$task) }}" class="rounded-xl border border-slate-300 px-6 py-3 font-semibold">Cancel</a><button class="rounded-xl bg-emerald-800 px-6 py-3 font-semibold text-white">Create Subtask</button></div>
 </form>
</div>
@endsection

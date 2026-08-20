@extends('layouts.app')
@section('title','Add Evidence')
@section('section-label','Daily Work')
@section('page-title','Add Evidence')
@section('content')
<div class="mx-auto max-w-4xl">
 <a href="{{ route('work-entries.show', $workEntry) }}" class="text-sm font-semibold text-emerald-700">&larr; Back to Daily Work Entry</a>
 <header class="mt-5"><p class="font-mono text-sm font-bold text-emerald-700">{{ $workEntry->entry_code }}</p><h2 class="mt-2 text-3xl font-bold">Add Evidence</h2><p class="mt-2 text-slate-600">Attach proof or output to this recorded work session.</p></header>
 @if($errors->any())<div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 <section class="mt-7 rounded-2xl border border-slate-200 bg-slate-50 p-6"><dl class="grid gap-5 sm:grid-cols-2">@foreach([['Daily Work Entry',$workEntry->entry_code],['Work Date',$workEntry->work_date->format('d F Y')],['Project',$workEntry->project->project_code.' — '.$workEntry->project->title],['Task',$workEntry->task->task_code.' — '.$workEntry->task->title],['Subtask',$workEntry->subtask ? $workEntry->subtask->subtask_code.' — '.$workEntry->subtask->title : 'Direct task entry']] as [$label,$value])<div><dt class="text-xs font-bold uppercase text-slate-500">{{ $label }}</dt><dd class="mt-1 font-semibold text-slate-800">{{ $value }}</dd></div>@endforeach</dl></section>
 <form method="POST" action="{{ route('work-entries.evidence.store', $workEntry) }}" enctype="multipart/form-data" class="mt-7 space-y-6">@csrf
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"><div class="grid gap-6">
   <label class="text-sm font-semibold">Evidence Title<input name="title" required maxlength="255" value="{{ old('title') }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
   <fieldset><legend class="text-sm font-semibold">Evidence Type</legend><div class="mt-3 flex flex-wrap gap-5"><label class="flex items-center gap-2"><input type="radio" name="type" value="file" data-type @checked(old('type','file')==='file')> File Upload</label><label class="flex items-center gap-2"><input type="radio" name="type" value="link" data-type @checked(old('type')==='link')> Link / URL</label></div></fieldset>
   <label class="text-sm font-semibold">Description (optional)<textarea name="description" maxlength="5000" rows="4" class="mt-2 w-full rounded-xl border-slate-300">{{ old('description') }}</textarea></label>
   <div data-file-field><label class="text-sm font-semibold">Evidence File<input type="file" name="evidence_file" class="mt-2 block w-full rounded-xl border border-slate-300 p-3" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.webp"><span class="mt-2 block text-xs font-normal text-slate-500">PDF, Office documents, CSV, text, JPG, PNG or WebP; maximum 10 MB.</span></label></div>
   <div data-link-field><label class="text-sm font-semibold">Evidence URL<input type="url" name="url" value="{{ old('url') }}" placeholder="https://example.com/resource" class="mt-2 w-full rounded-xl border-slate-300"></label></div>
  </div></section>
  <div class="flex justify-end gap-3"><a href="{{ route('work-entries.show', $workEntry) }}" class="rounded-xl border border-slate-300 px-6 py-3 font-semibold">Cancel</a><button class="rounded-xl bg-emerald-800 px-6 py-3 font-semibold text-white">Add Evidence</button></div>
 </form>
</div>
<script>(()=>{const types=[...document.querySelectorAll('[data-type]')],file=document.querySelector('[data-file-field]'),link=document.querySelector('[data-link-field]'),sync=()=>{const value=types.find(input=>input.checked)?.value;file.hidden=value!=='file';link.hidden=value!=='link';file.querySelector('input').required=value==='file';link.querySelector('input').required=value==='link'};types.forEach(input=>input.addEventListener('change',sync));sync()})();</script>
@endsection

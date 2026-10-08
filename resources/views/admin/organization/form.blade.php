@extends('layouts.app')
@section('title',($record ? 'Edit ' : 'Add ').str($label)->singular())
@section('section-label','Organization Setup')
@section('page-title',($record ? 'Edit ' : 'Add ').str($label)->singular())
@section('content')
<div class="mx-auto max-w-3xl"><a href="{{ route('admin.organization.index',$entity) }}" class="text-sm font-semibold text-emerald-700">&larr; Back to {{ $label }}</a><form method="POST" action="{{ $record ? route('admin.organization.update',[$entity,$record->id]) : route('admin.organization.store',$entity) }}" class="form-card mt-5 space-y-5">@csrf @if($record)@method('PATCH')@endif
<div><h2 class="text-2xl font-bold">{{ $record ? 'Update' : 'Create' }} {{ str($label)->singular() }}</h2><p class="mt-1 text-sm text-slate-500">Fields marked with an asterisk are required.</p></div>
<x-validation-summary title="Please correct the form." />
@if($entity==='libraries')<label class="block"><span class="field-label">Campus *</span><select name="campus_id" required><option value="">Select campus</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string)old('campus_id',$record?->campus_id)===(string)$campus->id)>{{ $campus->name }}</option>@endforeach</select></label>@endif
<div class="grid gap-5 sm:grid-cols-2"><label class="block"><span class="field-label">Name *</span><input name="name" required maxlength="255" value="{{ old('name',$record?->name) }}">@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>@if(in_array($entity,['campuses','libraries','positions']))<label class="block"><span class="field-label">Code {{ $entity==='campuses' ? '*' : '(optional)' }}</span><input name="code" maxlength="50" value="{{ old('code',$record?->code) }}"></label>@endif</div>
@if($entity==='campuses')<label class="block"><span class="field-label">Location</span><input name="location" value="{{ old('location',$record?->location) }}"></label>@endif
@if(in_array($entity,['campuses','libraries']))<div class="grid gap-5 sm:grid-cols-2"><label><span class="field-label">Email</span><input type="email" name="email" value="{{ old('email',$record?->email) }}"></label><label><span class="field-label">Phone</span><input name="phone" value="{{ old('phone',$record?->phone) }}"></label></div>@endif
@if(in_array($entity,['positions','project-categories']))<label class="block"><span class="field-label">Description</span><textarea name="description" rows="4" maxlength="3000">{{ old('description',$record?->description) }}</textarea></label>@endif
@if($entity==='positions')
@php($jobDetail = $record?->jobDetail)
<label class="block sm:max-w-xs"><span class="field-label">Rank (optional)</span><input type="number" name="sort_order" min="1" max="1000" value="{{ old('sort_order',$record?->sort_order) }}"><span class="mt-1 block text-xs text-slate-500">Lower numbers appear first in position lists.</span>@error('sort_order')<span class="field-error">{{ $message }}</span>@enderror</label>
<fieldset class="space-y-5 rounded-2xl border border-slate-200 bg-slate-50/60 p-5">
    <legend class="px-2 text-lg font-bold text-heading">Job description</legend>
    <p class="-mt-2 text-sm text-slate-500">Staff assigned to this position see this on their dashboard and under <strong>My Job Description</strong>, and are notified when it changes. Leave the purpose and duties empty to remove the job description.</p>
    <div class="grid gap-5 sm:grid-cols-3">
        <label class="block"><span class="field-label">Salary scale</span><input name="salary_scale" maxlength="50" placeholder="e.g. M5" value="{{ old('salary_scale',$jobDetail?->salary_scale) }}">@error('salary_scale')<span class="field-error">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="field-label">Reports to</span><input name="reports_to" maxlength="255" value="{{ old('reports_to',$jobDetail?->reports_to) }}">@error('reports_to')<span class="field-error">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="field-label">Responsible for</span><input name="responsible_for" maxlength="255" value="{{ old('responsible_for',$jobDetail?->responsible_for) }}">@error('responsible_for')<span class="field-error">{{ $message }}</span>@enderror</label>
    </div>
    <label class="block"><span class="field-label">Job purpose</span><textarea name="job_purpose" rows="3" maxlength="5000">{{ old('job_purpose',$jobDetail?->job_purpose) }}</textarea>@error('job_purpose')<span class="field-error">{{ $message }}</span>@enderror</label>
    <label class="block"><span class="field-label">Duties and responsibilities</span><textarea name="duties" rows="10" maxlength="20000" placeholder="One duty per line">{{ old('duties', implode(PHP_EOL, $jobDetail?->duties ?? [])) }}</textarea><span class="mt-1 block text-xs text-slate-500">One duty per line. Bullets or numbering at the start of a line are removed automatically.</span>@error('duties')<span class="field-error">{{ $message }}</span>@enderror</label>
</fieldset>
@endif
<label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$record?->is_active ?? true))><span><span class="block font-semibold">Active</span><span class="text-sm text-slate-500">Inactive records remain available for historical relationships but cannot be selected for new work.</span></span></label>
<div class="action-bar"><a href="{{ route('admin.organization.index',$entity) }}" class="rounded-xl border border-slate-300 px-5 py-2.5 font-semibold text-slate-700">Cancel</a><button class="rounded-xl bg-emerald-800 px-5 py-2.5 font-semibold text-white">{{ $record ? 'Save changes' : 'Create record' }}</button></div></form></div>
@endsection

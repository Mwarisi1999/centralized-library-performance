@props(['status'])
@php
    $normalized = str($status)->lower()->replace(' ', '_')->toString();
    $tone = match ($normalized) {
        'active', 'approved', 'completed', 'finalized' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'inactive', 'cancelled', 'suspended' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'returned', 'returned_for_correction', 'overdue' => 'bg-red-50 text-red-800 ring-red-200',
        'pending', 'pending_review', 'planned' => 'bg-amber-50 text-amber-800 ring-amber-200',
        default => 'bg-blue-50 text-blue-800 ring-blue-200',
    };
@endphp
<span {{ $attributes->class("inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {$tone}") }}>{{ str($status)->replace('_',' ')->title() }}</span>

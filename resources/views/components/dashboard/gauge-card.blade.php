@props(['label' => 'Completion Rate', 'chartId', 'rate' => 0])

@php($rate = round((float) $rate, 1))

{{-- Radial completion gauge; the number is server-rendered so it still reads without JavaScript. --}}
<article {{ $attributes->class('flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm') }}>
    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p>
    <div id="{{ $chartId }}" data-gauge data-rate="{{ $rate }}" class="flex flex-1 items-center justify-center" role="img" aria-label="{{ $label }} {{ number_format($rate, 1) }}%">
        <p class="py-10 text-4xl font-extrabold text-heading">{{ number_format($rate, 1) }}%</p>
    </div>
    @if(trim($slot) !== '')
        <p class="text-center text-sm text-slate-500">{{ $slot }}</p>
    @endif
</article>

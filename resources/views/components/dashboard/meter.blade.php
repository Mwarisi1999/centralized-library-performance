@props(['value' => 0, 'tone' => 'blue'])

@php($percent = max(0, min(100, (float) $value)))

<div {{ $attributes->class('flex min-w-28 items-center gap-2') }}>
    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200">
        <div @class([
            'h-full rounded-full',
            'bg-busitema-blue' => $tone === 'blue',
            'bg-busitema-gold' => $tone === 'gold',
            'bg-ink' => $tone === 'ink',
        ]) style="width: {{ $percent }}%"></div>
    </div>
    <span class="w-12 text-right text-xs font-bold text-heading">{{ number_format($percent, 1) }}%</span>
</div>

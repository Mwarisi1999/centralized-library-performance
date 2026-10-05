@props(['label', 'value', 'unit' => null, 'note' => null])

{{-- Charcoal feature card; the default slot renders as a footer line. --}}
<article {{ $attributes->class('flex flex-col rounded-2xl border-t-4 border-busitema-gold bg-ink p-5 text-white shadow-sm') }}>
    <div class="flex items-start justify-between gap-4">
        <p class="text-xs font-bold uppercase tracking-wider text-white/70">{{ $label }}</p>
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-busitema-gold/15 text-busitema-gold ring-1 ring-busitema-gold/30">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
        </span>
    </div>
    <p class="mt-1 text-3xl font-extrabold tracking-tight text-white">{{ $value }} @if($unit)<span class="text-lg font-semibold text-white/70">{{ $unit }}</span>@endif</p>
    @if($note)
        <p class="mt-1.5 text-sm text-white/70">{{ $note }}</p>
    @endif
    @if(trim($slot) !== '')
        <div class="mt-auto flex items-center gap-2 border-t border-white/15 pt-4 text-sm">
            <svg class="h-4 w-4 shrink-0 text-busitema-gold" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
            <span>{{ $slot }}</span>
        </div>
    @endif
</article>

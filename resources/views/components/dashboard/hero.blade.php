@props(['eyebrow' => null, 'title', 'description' => null, 'headingId' => null])

{{-- Charcoal-and-gold banner shared by every role dashboard. --}}
<div {{ $attributes->class('relative overflow-hidden rounded-2xl bg-ink p-6 pl-8 text-white shadow-sm sm:p-8 sm:pl-10') }}>
    <div class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle, #f9d028 1px, transparent 1px); background-size: 18px 18px;" aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-y-0 left-0 w-1.5 bg-busitema-gold" aria-hidden="true"></div>

    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            @if($eyebrow)
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-busitema-gold">{{ $eyebrow }}</p>
            @endif
            <h2 @if($headingId) id="{{ $headingId }}" @endif class="mt-2 text-2xl font-bold text-white sm:text-3xl">{{ $title }}</h2>
            @if($description)
                <p class="mt-2 max-w-2xl text-sm text-white/75">{{ $description }}</p>
            @endif
            @if(trim($slot) !== '')
                <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">{{ $slot }}</div>
            @endif
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>

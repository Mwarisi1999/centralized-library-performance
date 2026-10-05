@props(['title', 'subtitle' => null, 'flush' => false])

{{-- White content card with a consistent header; `flush` removes body padding for tables and lists. --}}
<section {{ $attributes->class('flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm') }}>
    <div @class(['flex flex-wrap items-start justify-between gap-3 px-5 pt-5 sm:px-6', 'border-b border-slate-200 pb-4' => $flush])>
        <div>
            <h3 class="text-lg font-bold">{{ $title }}</h3>
            @if($subtitle)
                <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($aside)
            <div class="shrink-0">{{ $aside }}</div>
        @endisset
    </div>

    <div @class(['flex-1', 'px-5 pb-5 pt-4 sm:px-6 sm:pb-6' => ! $flush])>{{ $slot }}</div>
</section>

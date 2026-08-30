@php
    $isLinked = isset($route) && Route::has($route);
    $isActive = $active ?? false;
@endphp

@if($isLinked)
    <a
        href="{{ route($route) }}"
        title="{{ $label }}"
        data-sidebar-item
        @if($isActive) aria-current="page" @endif
        @class([
            'group flex min-h-10 items-center gap-3 rounded-xl border-l-4 px-3 py-2.5 text-sm font-semibold transition-all duration-200',
            'border-amber-400 bg-white text-blue-700 shadow-sm' => $isActive,
            'border-transparent text-white hover:bg-white/10' => ! $isActive,
        ])
    >
        <span class="h-2 w-2 shrink-0 rounded-full {{ $isActive ? 'bg-amber-400' : 'bg-white/60 group-hover:bg-white' }}"></span>
        <span data-sidebar-label class="min-w-0 truncate">{{ $label }}</span>
    </a>
@else
    <div data-sidebar-item class="flex min-h-10 items-center gap-3 rounded-xl border-l-4 border-transparent px-3 py-2.5 text-sm font-medium text-white/60" title="{{ $label }} — Coming soon">
        <span class="h-2 w-2 shrink-0 rounded-full bg-white/35"></span>
        <span data-sidebar-label class="min-w-0 truncate">{{ $label }}</span>
        <span data-sidebar-label class="ml-auto rounded-full bg-white/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-white/60">Soon</span>
    </div>
@endif

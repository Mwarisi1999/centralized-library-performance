@php
    $isLinked = isset($route) && Route::has($route);
    $isActive = $active ?? false;
@endphp

@if($isLinked)
    <a href="{{ route($route) }}" @class([
        'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
        'bg-emerald-700 text-white shadow-sm' => $isActive,
        'text-slate-300 hover:bg-white/10 hover:text-white' => ! $isActive,
    ])>
        <span class="h-2 w-2 rounded-full {{ $isActive ? 'bg-white' : 'bg-emerald-600 group-hover:bg-emerald-400' }}"></span>
        <span>{{ $label }}</span>
    </a>
@else
    <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-400" title="Coming soon">
        <span class="h-2 w-2 rounded-full bg-slate-700"></span>
        <span>{{ $label }}</span>
        <span class="ml-auto rounded-full bg-white/5 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-slate-500">Soon</span>
    </div>
@endif

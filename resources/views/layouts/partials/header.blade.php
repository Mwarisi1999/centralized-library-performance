@php
    $headerUser = auth()->user();
    $nameParts = collect(preg_split('/\s+/', trim($headerUser->name)))->filter()->values();
    $initials = str($nameParts->first() ?? '?')->substr(0, 1)
        .($nameParts->count() > 1 ? str($nameParts->last())->substr(0, 1) : '');
@endphp

<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="flex h-20 items-center gap-4 px-4 sm:px-6 lg:px-8">
        <button type="button" data-sidebar-open class="rounded-xl border border-slate-200 p-2.5 text-slate-600 hover:bg-slate-50 lg:hidden" aria-controls="app-sidebar" aria-expanded="false" aria-label="Open navigation">
            <span class="block h-0.5 w-5 bg-current"></span>
            <span class="mt-1.5 block h-0.5 w-5 bg-current"></span>
            <span class="mt-1.5 block h-0.5 w-5 bg-current"></span>
        </button>

        <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-semibold uppercase tracking-wider text-emerald-700">@yield('section-label', 'Centralized Library')</p>
            <h1 class="truncate text-lg font-bold text-slate-900 sm:text-xl">@yield('page-title', 'Dashboard')</h1>
        </div>

        <div class="relative" data-user-menu>
            <button type="button" data-user-menu-button class="flex items-center gap-3 rounded-xl p-1.5 text-left hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-600" aria-expanded="false" aria-haspopup="menu">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold uppercase text-emerald-800">{{ $initials }}</span>
                <span class="hidden min-w-0 sm:block">
                    <span class="block max-w-48 truncate text-sm font-semibold text-slate-800">{{ $headerUser->name }}</span>
                    <span class="block max-w-48 truncate text-xs text-slate-500">{{ $headerUser->getRoleNames()->join(', ') ?: 'User' }}</span>
                </span>
                <span class="hidden text-xs text-slate-400 sm:block" aria-hidden="true">&#9662;</span>
            </button>

            <div data-user-menu-panel class="absolute right-0 mt-2 hidden w-60 overflow-hidden rounded-xl border border-slate-200 bg-white py-2 shadow-xl" role="menu">
                <div class="border-b border-slate-100 px-4 py-3 sm:hidden">
                    <p class="truncate text-sm font-semibold text-slate-800">{{ $headerUser->name }}</p>
                    <p class="mt-1 truncate text-xs text-slate-500">{{ $headerUser->getRoleNames()->join(', ') ?: 'User' }}</p>
                </div>
                <div class="flex items-center justify-between px-4 py-2.5 text-sm text-slate-400" aria-disabled="true">
                    <span>My Profile</span>
                    <span class="text-[9px] font-bold uppercase tracking-wider">Soon</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2.5 text-left text-sm font-semibold text-red-700 hover:bg-red-50" role="menuitem">Sign out</button>
                </form>
            </div>
        </div>
    </div>
</header>

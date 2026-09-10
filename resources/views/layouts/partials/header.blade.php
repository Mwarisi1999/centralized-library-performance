@php
    $headerUser = auth()->user();
    $headerDate = now()->timezone(config('app.timezone'))->format('D, d M Y');
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

        <time datetime="{{ now()->timezone(config('app.timezone'))->toDateString() }}" class="hidden whitespace-nowrap text-sm font-medium text-slate-500 xl:block">{{ $headerDate }}</time>

        <div class="relative shrink-0" data-notification-menu>
            @php($headerUnread = $headerUser->unreadNotifications()->count())
            <button type="button" data-notification-menu-button class="relative rounded-xl border border-slate-200 p-2.5 text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-600" aria-label="Notifications" aria-expanded="false">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                @if($headerUnread)<span class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-red-600 px-1.5 py-0.5 text-center text-[10px] font-bold text-white">{{ $headerUnread > 99 ? '99+' : $headerUnread }}</span>@endif
            </button>
            <div data-notification-menu-panel class="absolute right-0 mt-2 hidden w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3"><div><p class="font-bold">Notifications</p><p class="text-xs text-slate-500">{{ $headerUnread }} unread</p></div><a href="{{ route('notifications.index') }}" class="text-sm font-semibold text-emerald-700">View all</a></div>
                <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto">@forelse($headerUser->notifications()->latest()->limit(5)->get() as $notification)<form method="POST" action="{{ route('notifications.read',$notification) }}">@csrf<button class="w-full px-4 py-3 text-left hover:bg-slate-50"><span class="block text-sm font-semibold {{ $notification->read_at ? 'text-slate-600' : 'text-slate-900' }}">{{ data_get($notification->data,'title','Notification') }}</span><span class="mt-1 block text-xs leading-5 text-slate-500">{{ data_get($notification->data,'message') }}</span><span class="mt-1 block text-[11px] text-slate-400">{{ $notification->created_at->diffForHumans() }}</span></button></form>@empty<p class="px-4 py-8 text-center text-sm text-slate-500">No notifications yet.</p>@endforelse</div>
            </div>
        </div>

        <div class="relative" data-user-menu>
            <button type="button" data-user-menu-button class="flex items-center gap-3 rounded-xl p-1.5 text-left hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-600" aria-expanded="false" aria-haspopup="menu">
                <x-user-avatar :user="$headerUser" />
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
                <a href="{{ route('profile.show') }}" class="block px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-busitema-blue" role="menuitem">My Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2.5 text-left text-sm font-semibold text-red-700 hover:bg-red-50" role="menuitem">Sign out</button>
                </form>
            </div>
        </div>
    </div>
</header>

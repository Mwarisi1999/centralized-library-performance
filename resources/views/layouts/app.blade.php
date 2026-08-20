<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | Centralized Library Staff Performance System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-100 text-slate-900 antialiased">
    <div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-slate-950/60 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

    @include('layouts.partials.sidebar')

    <div class="min-h-screen lg:pl-72">
        @include('layouts.partials.header')

        <main id="main-content" class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            @include('layouts.partials.flash-messages')
            @yield('content')
        </main>
    </div>
</body>
</html>

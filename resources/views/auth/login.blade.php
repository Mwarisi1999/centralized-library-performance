<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Centralized Library Staff Performance System</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100">

    <div class="min-h-screen flex">

        {{-- Branding panel --}}
        <div class="hidden lg:flex lg:w-1/2 bg-emerald-900 text-white p-16 flex-col justify-between">

            <div>
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-xl bg-white/10 flex items-center justify-center font-bold text-xl">
                        BU
                    </div>

                    <div>
                        <p class="text-lg font-semibold">
                            Busitema University
                        </p>

                        <p class="text-sm text-emerald-100">
                            University Library
                        </p>
                    </div>
                </div>
            </div>

            <div class="max-w-xl">
                <p class="text-emerald-300 font-semibold mb-4">
                    STAFF PERFORMANCE MANAGEMENT
                </p>

                <h1 class="text-4xl xl:text-5xl font-bold leading-tight">
                    Centralized Library Staff Performance System
                </h1>

                <p class="mt-6 text-lg text-emerald-100 leading-relaxed">
                    A centralized platform for managing projects, daily work,
                    timesheets, performance reporting and monitoring across
                    Busitema University libraries.
                </p>
            </div>

            <p class="text-sm text-emerald-200">
                Busitema University Library
            </p>

        </div>

        {{-- Login panel --}}
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12">

            <div class="w-full max-w-md">

                {{-- Mobile branding --}}
                <div class="lg:hidden mb-10 text-center">
                    <div class="mx-auto h-14 w-14 rounded-xl bg-emerald-900 text-white flex items-center justify-center font-bold text-xl">
                        BU
                    </div>

                    <h1 class="mt-4 text-xl font-bold text-slate-900">
                        Busitema University Library
                    </h1>
                </div>

                <div class="mb-8">
                    <p class="text-sm font-semibold text-emerald-700">
                        WELCOME BACK
                    </p>

                    <h2 class="mt-2 text-3xl font-bold text-slate-900">
                        Sign in to your account
                    </h2>

                    <p class="mt-2 text-slate-500">
                        Enter your university library account details.
                    </p>
                </div>

                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-700">
                            Email address
                        </label>

                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="name@busitema.ac.ug"
                            class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100"
                        >
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label for="password" class="block text-sm font-semibold text-slate-700">
                                Password
                            </label>

                            <a
                                href="{{ route('password.request') }}"
                                class="text-sm font-semibold text-emerald-700 hover:text-emerald-900"
                            >
                                Forgot password?
                            </a>
                        </div>

                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100"
                        >
                    </div>

                    <label class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            name="remember"
                            class="h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
                        >

                        <span class="text-sm text-slate-600">
                            Keep me signed in
                        </span>
                    </label>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-emerald-800 px-5 py-3.5 font-semibold text-white transition hover:bg-emerald-900 focus:outline-none focus:ring-4 focus:ring-emerald-200"
                    >
                        Sign in
                    </button>
                </form>

                <div class="mt-8 border-t border-slate-200 pt-6 text-center">
                    <p class="text-sm text-slate-500">
                        Accounts are managed by the University Library.
                    </p>

                    <p class="mt-1 text-sm text-slate-500">
                        Contact the system administrator if you need assistance.
                    </p>
                </div>

            </div>

        </div>

    </div>

</body>
</html>
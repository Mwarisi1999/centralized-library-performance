<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Centralized Library Staff Performance System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100">

<div class="min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm">

        <p class="text-sm font-semibold text-emerald-700">
            ACCOUNT SECURITY
        </p>

        <h1 class="mt-2 text-3xl font-bold text-slate-900">
            Reset your password
        </h1>

        @if ($errors->any())
            <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-6">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700">
                    Email address
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', $request->email) }}"
                    required
                    autocomplete="email"
                    class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3"
                >
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700">
                    New password
                </label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3"
                >
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-slate-700">
                    Confirm new password
                </label>

                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-emerald-800 px-5 py-3.5 font-semibold text-white hover:bg-emerald-900"
            >
                Reset password
            </button>
        </form>

    </div>

</div>

</body>
</html>
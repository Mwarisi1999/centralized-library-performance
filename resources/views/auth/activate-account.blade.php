<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate Account | Centralized Library Staff Performance System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100">
<main class="flex min-h-screen items-center justify-center p-6">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-emerald-900 text-xl font-bold text-white">BU</div>

        <div class="mt-7 text-center">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Account Security</p>
            <h1 class="mt-2 text-3xl font-bold text-slate-900">Activate your account</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500">Create a secure password to finish activating your Centralized Library Staff Performance System account.</p>
        </div>

        <div class="mt-6 rounded-xl bg-slate-50 px-4 py-3 text-sm">
            <p class="font-semibold text-slate-800">{{ $user->name }}</p>
            <p class="mt-1 text-slate-500">{{ $user->email }}</p>
        </div>

        @if($errors->any())
            <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('account.activate.store', $token) }}" class="mt-7 space-y-6">
            @csrf

            <label class="block text-sm font-semibold text-slate-700">New password
                <input name="password" type="password" required autofocus autocomplete="new-password" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 font-normal outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100">
                <span class="mt-2 block text-xs font-normal leading-5 text-slate-500">Use at least 12 characters with uppercase, lowercase, number, and symbol.</span>
            </label>

            <label class="block text-sm font-semibold text-slate-700">Confirm password
                <input name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 font-normal outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100">
            </label>

            <button type="submit" class="w-full rounded-xl bg-emerald-800 px-5 py-3.5 font-semibold text-white transition hover:bg-emerald-900 focus:outline-none focus:ring-4 focus:ring-emerald-200">Activate Account</button>
        </form>

        <p class="mt-7 text-center text-xs leading-5 text-slate-500">This invitation is private, expires after 48 hours, and can only be used once.</p>
    </div>
</main>
</body>
</html>

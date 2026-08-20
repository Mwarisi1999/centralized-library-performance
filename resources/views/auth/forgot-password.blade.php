<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Centralized Library Staff Performance System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100">

<div class="min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm">

        <p class="text-sm font-semibold text-emerald-700">
            PASSWORD RECOVERY
        </p>

        <h1 class="mt-2 text-3xl font-bold text-slate-900">
            Forgot your password?
        </h1>

        <p class="mt-3 text-slate-500">
            Enter your account email address and we will send you a password reset link.
        </p>

        @if (session('status'))
            <div class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-6">
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
                    class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-emerald-800 px-5 py-3.5 font-semibold text-white hover:bg-emerald-900"
            >
                Send password reset link
            </button>
        </form>

        <div class="mt-6 text-center">
            <a
                href="{{ route('login') }}"
                class="text-sm font-semibold text-emerald-700 hover:text-emerald-900"
            >
                Back to login
            </a>
        </div>

    </div>

</div>

</body>
</html>
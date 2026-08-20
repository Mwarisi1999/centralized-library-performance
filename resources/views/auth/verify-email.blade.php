<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email | Centralized Library Staff Performance System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100">

<div class="min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-lg rounded-2xl bg-white p-8 shadow-sm">

        <p class="text-sm font-semibold text-emerald-700">
            EMAIL VERIFICATION
        </p>

        <h1 class="mt-2 text-3xl font-bold text-slate-900">
            Verify your email address
        </h1>

        <p class="mt-4 text-slate-600">
            Before continuing, please verify your email address using the verification link sent to you.
        </p>

        @if (session('status') === 'verification-link-sent')
            <div class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                A new verification link has been sent to your email address.
            </div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}" class="mt-8">
            @csrf

            <button
                type="submit"
                class="rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900"
            >
                Resend verification email
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf

            <button
                type="submit"
                class="text-sm font-semibold text-slate-600 hover:text-slate-900"
            >
                Sign out
            </button>
        </form>

    </div>

</div>

</body>
</html>
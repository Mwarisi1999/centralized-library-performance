<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->account_status === 'active', 403);
        abort_if($user->staffProfile()->where('status', '!=', 'active')->exists(), 403);

        return $next($request);
    }
}

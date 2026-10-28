<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(auth('sanctum')->user()?->is_blocked, 403, 'User is blocked.');

        return $next($request);
    }
}

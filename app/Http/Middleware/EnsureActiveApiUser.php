<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveApiUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = auth('api');

        if (! $guard->check()) {
            return $next($request);
        }

        $user = $guard->user();

        if (data_get($user, 'is_active') === false) {

            return response()->json([
                'message' => 'Your account is disabled. Please contact your business owner.',
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Auth\Enums\Role;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $guard = auth('api');

        if (! $guard->check()) {
            return $next($request);
        }

        $user = $guard->user();
        $userRole = data_get($user, 'role');
        $userRoleValue = $userRole instanceof Role ? $userRole->value : $userRole;
        $allowedRoles = array_map(
            static fn (string $role): string => Role::tryFrom($role)?->value ?? $role,
            $roles,
        );

        if (! in_array($userRoleValue, $allowedRoles, true)) {
            return response()->json([
                'message' => 'You do not have the required role to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}

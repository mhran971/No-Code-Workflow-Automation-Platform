<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Auth\Enums\Role;
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

        if ($user && $user->tenant_id) {
            $tenant = $user->relationLoaded('tenant') ? $user->tenant : $user->tenant()->first();

            if ($tenant) {
                if (isset($tenant->is_active) && ! (bool) $tenant->is_active) {
                    return response()->json([
                        'message' => 'This tenant workspace has been deactivated. Please contact platform support.',
                    ], 403);
                }

                if (! empty($tenant->maintenance_mode) && ($user->role ?? null) !== Role::SuperAdmin) {
                    return response()->json([
                        'message' => 'Tenant workspace is currently under maintenance.',
                        'notice' => $tenant->maintenance_message ?? 'Scheduled maintenance is in progress for this workspace. Please try again shortly.',
                    ], 503);
                }
            }
        }

        return $next($request);
    }
}

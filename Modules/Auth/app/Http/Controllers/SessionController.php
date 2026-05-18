<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Resources\LoginSuccessResource;
use Modules\Auth\Models\User;
use Tymon\JWTAuth\JWTGuard;

class SessionController extends Controller
{
    /**
     * Authenticate the user and return a JWT (with tenant in claims).
     *
     * @unauthenticated
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user !== null && array_key_exists('is_active', $user->getAttributes()) && ! (bool) $user->is_active) {
            return response()->json([
                'message' => 'Your account is disabled. Please contact your business owner.',
            ], 403);
        }

        if (Schema::hasColumn('users', 'is_active')) {
            $credentials['is_active'] = true;
        }

        /** @var JWTGuard $guard */
        $guard = auth('api');

        if (! $token = $guard->attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        /** @var User $authenticatedUser */
        $authenticatedUser = $guard->user();
        $authenticatedUser->load('tenant');

        return (new LoginSuccessResource($authenticatedUser))
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Invalidate the current JWT and log the user out.
     */
    public function destroy(): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        $guard->logout();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}

<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Resources\LoginSuccessResource;

class LoginController extends Controller
{
    /**
     * Authenticate the user and return a JWT (with tenant in claims).
     *
     * @unauthenticated
     */
    public function __invoke(LoginRequest $request): JsonResponse|LoginSuccessResource
    {
        $credentials = $request->only('email', 'password');

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $user = auth('api')->user();
        $user->load('tenant');

        return (new LoginSuccessResource($user))
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode(200);
    }
}

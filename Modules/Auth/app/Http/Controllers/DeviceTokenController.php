<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Models\DeviceToken;

/**
 * Register and remove FCM device tokens for push notifications.
 */
class DeviceTokenController extends Controller
{
    /**
     * Register (or refresh) an FCM token for the authenticated user.
     *
     * Uses updateOrCreate on the token column so re-sending the same
     * token simply updates device_name / platform / updated_at.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['required', 'string', 'in:ios,android'],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $request->input('token')],
            [
                'user_id' => auth('api')->id(),
                'device_name' => $request->input('device_name'),
                'platform' => $request->input('platform'),
            ]
        );

        return response()->json(['message' => 'Device registered.'], 201);
    }

    /**
     * Remove an FCM token (e.g. on logout).
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        DeviceToken::query()
            ->where('user_id', auth('api')->id())
            ->where('token', $request->input('token'))
            ->delete();

        return response()->json(['message' => 'Device unregistered.']);
    }
}

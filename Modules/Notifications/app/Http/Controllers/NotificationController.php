<?php

namespace Modules\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Models\User;
use Modules\Notifications\Services\NotificationService;

/**
 * Mobile notification endpoints.
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * List notifications for the authenticated user (paginated, newest first).
     *
     * Query params:
     *   type — filter by notification type
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->actor();

        $notifications = $this->notificationService->listForUser(
            user: $user,
            type: $request->input('type'),
            perPage: 20
        );

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'from' => $notifications->firstItem(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'to' => $notifications->lastItem(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(string $notificationId): JsonResponse
    {
        $user = $this->actor();

        $success = $this->notificationService->markRead($user, $notificationId);

        if (! $success) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        return response()->json(null, 204);
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllRead(): JsonResponse
    {
        $user = $this->actor();

        $count = $this->notificationService->markAllRead($user);

        return response()->json(['updated_count' => $count]);
    }

    /**
     * Return unread notification count (badge).
     */
    public function unreadCount(): JsonResponse
    {
        $user = $this->actor();

        return response()->json([
            'unread_count' => $this->notificationService->getUnreadCount($user),
        ]);
    }

    private function actor(): User
    {
        return auth('api')->user();
    }
}

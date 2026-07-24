<?php

namespace Modules\Notifications\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Modules\Auth\Models\User;

class NotificationRepository
{
    /**
     * Get paginated notifications for a given user, optionally filtered by type.
     */
    public function getForUser(User $user, ?string $type = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $user->notifications();

        if ($type !== null && $type !== '') {
            $query->whereJsonContains('data->type', $type);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Find a specific notification belonging to a user.
     */
    public function findForUser(User $user, string $notificationId): ?DatabaseNotification
    {
        return $user->notifications()->where('id', $notificationId)->first();
    }

    /**
     * Mark a single database notification as read.
     */
    public function markAsRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * Mark all unread notifications as read for a user and return the updated count.
     */
    public function markAllAsRead(User $user): int
    {
        $count = $user->unreadNotifications()->count();
        $user->unreadNotifications->markAsRead();

        return $count;
    }

    /**
     * Get unread notifications count for a user.
     */
    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }
}

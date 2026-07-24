<?php

namespace Modules\Notifications\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Modules\Auth\Models\User;
use Modules\Notifications\Repositories\NotificationRepository;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $repository,
    ) {}

    /**
     * List user notifications with formatted payload.
     */
    public function listForUser(User $user, ?string $type = null, int $perPage = 20): LengthAwarePaginator
    {
        $notifications = $this->repository->getForUser($user, $type, $perPage);

        $notifications->getCollection()->transform(fn (DatabaseNotification $n) => [
            'id' => $n->id,
            'type' => $n->data['type'] ?? null,
            'title' => $n->data['title'] ?? null,
            'body' => $n->data['body'] ?? null,
            'data' => collect($n->data)->except(['type', 'title', 'body'])->all(),
            'is_read' => $n->read_at !== null,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ]);

        return $notifications;
    }

    /**
     * Mark a specific user notification as read.
     */
    public function markRead(User $user, string $notificationId): bool
    {
        $notification = $this->repository->findForUser($user, $notificationId);

        if (! $notification) {
            return false;
        }

        $this->repository->markAsRead($notification);

        return true;
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllRead(User $user): int
    {
        return $this->repository->markAllAsRead($user);
    }

    /**
     * Get total unread count.
     */
    public function getUnreadCount(User $user): int
    {
        return $this->repository->getUnreadCount($user);
    }
}

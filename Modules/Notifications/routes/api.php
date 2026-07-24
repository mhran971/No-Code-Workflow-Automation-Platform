<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\NotificationController;

// Mobile — notification inbox & badge count.
Route::prefix('v1/mobile/notifications')->middleware(['auth:api', 'active.user'])->group(function (): void {
    // Static routes first — before the {notification} wildcard.
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('mobile.notifications.unread-count');
    Route::patch('/read-all', [NotificationController::class, 'markAllRead'])->name('mobile.notifications.read-all');

    Route::get('/', [NotificationController::class, 'index'])->name('mobile.notifications.index');
    Route::patch('/{notification}/read', [NotificationController::class, 'markRead'])->name('mobile.notifications.read');
});

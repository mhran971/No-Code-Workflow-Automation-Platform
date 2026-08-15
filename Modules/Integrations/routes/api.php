<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Http\Controllers\ClickUpController;
use Modules\Integrations\Http\Controllers\IntegrationsController;

Route::prefix('v1/integrations')->name('integrations.')->group(function () {
    Route::middleware(['auth:api', 'role:business_owner'])->group(function () {
        Route::post('/{provider}/connect', [IntegrationsController::class, 'connect'])
            ->name('connect');
        Route::delete('/{provider}/disconnect', [IntegrationsController::class, 'disconnect'])
            ->name('disconnect');
        Route::get('/', [IntegrationsController::class, 'index'])
            ->name('index');
    });

    Route::middleware(['auth:api', 'role:business_owner,manager'])->group(function () {
        Route::get('/clickup/workspaces', [ClickUpController::class, 'workspaces'])
            ->name('clickup.workspaces');
        Route::get('/clickup/workspaces/{workspaceId}/lists', [ClickUpController::class, 'lists'])
            ->name('clickup.lists');
    });

    Route::get('/{provider}/callback', [IntegrationsController::class, 'callback'])
        ->name('callback');
});

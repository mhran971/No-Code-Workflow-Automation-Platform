<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Http\Controllers\IntegrationsController;

Route::prefix('v1/integrations')->name('integrations.')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Route::post('/{provider}/connect', [IntegrationsController::class, 'connect'])
            ->name('connect');
        Route::delete('/{provider}/disconnect', [IntegrationsController::class, 'disconnect'])
            ->name('disconnect');
        Route::get('/', [IntegrationsController::class, 'index'])
            ->name('index');
    });

    Route::get('/{provider}/callback', [IntegrationsController::class, 'callback'])
        ->name('callback');
});

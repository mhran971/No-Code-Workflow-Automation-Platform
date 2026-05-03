<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Http\Controllers\IntegrationsController;

Route::prefix('v1/integrations')->name("integrations")->group(function () {
    Route::get('/clickup/connect/{id}', [IntegrationsController::class, 'clickupConnect'])
        ->name('.clickup.connect');

    Route::get('/clickup/callback', [IntegrationsController::class, 'clickupCallback'])
        ->name('.clickup.callback');
    Route::get('/connections', [IntegrationsController::class, 'connections']);
});
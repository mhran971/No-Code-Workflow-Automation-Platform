<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Http\Controllers\IntegrationsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('integrations', IntegrationsController::class)->names('integrations');
});

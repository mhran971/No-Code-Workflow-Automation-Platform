<?php

use Illuminate\Support\Facades\Route;
use Modules\Team\Http\Controllers\TeamController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('teams', TeamController::class)->names('team');
});

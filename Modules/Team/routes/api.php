<?php

use Illuminate\Support\Facades\Route;
use Modules\Team\app\Http\Controllers\TeamController;


Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('teams', TeamController::class)->except(['show']);
    // Custom actions for members
    Route::post('teams/{team}/members', [TeamController::class, 'addMember']);
    Route::delete('teams/{team}/members', [TeamController::class, 'removeMember']);
});

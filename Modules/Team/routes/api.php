<?php

use Illuminate\Support\Facades\Route;
use Modules\Team\app\Http\Controllers\TeamController;


Route::prefix('v1')->group(function () { //middleware(['auth:sanctum'])->
    Route::apiResource('teams', TeamController::class)->except(['show']);
    // Custom actions for members
    Route::post('teams/{team}/members', [TeamController::class, 'addMember']);
    Route::delete('teams/{team}/members', [TeamController::class, 'removeMember']);
});

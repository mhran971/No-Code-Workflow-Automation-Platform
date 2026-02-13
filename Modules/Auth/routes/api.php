<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\RegisterController;

Route::prefix('v1')->group(function () {
    Route::post('register', RegisterController::class)->name('register');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::apiResource('auths', AuthController::class)->names('auth');
    });
});

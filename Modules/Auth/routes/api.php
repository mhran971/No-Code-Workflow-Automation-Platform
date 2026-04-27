<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\RegisterController;
use Modules\Auth\Http\Controllers\SessionController;

Route::prefix('v1')->group(function () {
    Route::post('register', RegisterController::class)->name('register');
    Route::post('login', [SessionController::class, 'store'])->name('login');

    Route::middleware(['auth:api', 'active.user'])->group(function () {
        Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
    });
});
// test for ci/cd

<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\DeviceTokenController;
use Modules\Auth\Http\Controllers\RegisterController;
use Modules\Auth\Http\Controllers\SessionController;

Route::prefix('v1')->group(function () {
    Route::post('register', RegisterController::class)->name('register');
    Route::post('login', [SessionController::class, 'store'])->name('login');

    Route::middleware(['auth:api', 'active.user'])->group(function () {
        Route::get('me', [SessionController::class, 'me'])->name('me');
        Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
        Route::post('mobile/device-tokens', [DeviceTokenController::class, 'store'])->name('mobile.device-tokens.store');
        Route::delete('mobile/device-tokens', [DeviceTokenController::class, 'destroy'])->name('mobile.device-tokens.destroy');
    });
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\Team\Http\Controllers\MemberMangementController;

Route::prefix('v1/team')->middleware(['auth:api', 'active.user'])->group(function () {
    Route::post('users', MemberMangementController::class)->name('team.users.create');
    Route::patch('users/{user}/disable', [MemberMangementController::class, 'disable'])->name('team.users.disable');
    Route::patch('users/{user}/enable', [MemberMangementController::class, 'enable'])->name('team.users.enable');
    Route::delete('users/{user}', [MemberMangementController::class, 'destroy'])->name('team.users.delete');
});

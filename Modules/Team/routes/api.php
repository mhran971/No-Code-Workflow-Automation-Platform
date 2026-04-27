<?php

use Illuminate\Support\Facades\Route;
use Modules\Team\Http\Controllers\MemberMangementController;
use Modules\Team\Http\Controllers\TeamManagementController;
use Modules\Team\Http\Controllers\TeamMemberController;

Route::prefix('v1/team')->middleware(['auth:api', 'active.user'])->group(function () {
    Route::get('users', [MemberMangementController::class, 'index'])->name('team.users.index');
    Route::get('manager-candidates', [MemberMangementController::class, 'managerCandidates'])->name('team.users.manager-candidates');
    Route::get('teams', [TeamManagementController::class, 'index'])->name('team.teams.index');
    Route::post('teams', [TeamManagementController::class, 'store'])->name('team.teams.create');
    Route::get('teams/{team}', [TeamManagementController::class, 'show'])->name('team.teams.show');
    Route::patch('teams/{team}', [TeamManagementController::class, 'update'])->name('team.teams.update');
    Route::patch('teams/{team}/manager', [TeamManagementController::class, 'updateManager'])->name('team.teams.manager.update');

    Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->name('team.teams.members.add');
    Route::delete('teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('team.teams.members.remove');

    Route::post('users', MemberMangementController::class)->name('team.users.create');
    Route::patch('users/{user}/disable', [MemberMangementController::class, 'disable'])->name('team.users.disable');
    Route::patch('users/{user}/enable', [MemberMangementController::class, 'enable'])->name('team.users.enable');
    Route::delete('users/{user}', [MemberMangementController::class, 'destroy'])->name('team.users.delete');
});

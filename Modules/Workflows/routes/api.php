<?php

use Illuminate\Support\Facades\Route;
use Modules\Workflows\Http\Controllers\NodeController;
use Modules\Workflows\Http\Controllers\WorkflowController;

Route::prefix('v1/workflows')->middleware(['auth:api', 'active.user'])->group(function (): void {
    Route::get('/nodes', [NodeController::class, 'index'])->name('workflows.nodes.index');
    Route::post('/validate', [WorkflowController::class, 'validateDefinition'])->name('workflows.definition.validate');
    Route::get('/', [WorkflowController::class, 'index'])->name('workflows.index');
    Route::post('/', [WorkflowController::class, 'store'])->name('workflows.store');
    Route::get('/templates', [WorkflowController::class, 'templates'])->name('workflows.templates.index');
    Route::post('/proposals/ai', [WorkflowController::class, 'proposal'])->name('workflows.proposals.ai');
    Route::get('/{workflow}', [WorkflowController::class, 'show'])->name('workflows.show');
    Route::patch('/{workflow}/draft', [WorkflowController::class, 'updateDraft'])->name('workflows.draft.update');
    Route::post('/{workflow}/publish', [WorkflowController::class, 'publish'])->name('workflows.publish');
    Route::get('/{workflow}/versions', [WorkflowController::class, 'versions'])->name('workflows.versions.index');
    Route::patch('/{workflow}/status', [WorkflowController::class, 'updateStatus'])->name('workflows.status.update');
    Route::delete('/{workflow}', [WorkflowController::class, 'destroy'])->name('workflows.destroy');
    Route::delete('/{workflow}/purge', [WorkflowController::class, 'purge'])->name('workflows.purge');
    Route::post('/{workflow}/trigger/webhook', [WorkflowController::class, 'triggerWebhook'])->name('workflows.trigger.webhook');
});

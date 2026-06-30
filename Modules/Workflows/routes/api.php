<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Modules\Workflows\Http\Controllers\NodeController;
use Modules\Workflows\Http\Controllers\WorkflowController;
use Modules\Workflows\Http\Controllers\WorkflowInstanceController;
use Modules\Workflows\Http\Controllers\WorkflowTaskController;
use Modules\Workflows\Http\Controllers\WorkflowTriggerController;

// Broadcasting auth endpoint — uses auth:api so JWT users can subscribe to private channels.
// The default Broadcast::routes() only works with the web guard.
Route::middleware(['auth:api', 'active.user'])
    ->post('broadcasting/auth', function (Request $request) {
        auth()->shouldUse('api');

        return Broadcast::auth($request);
    })->name('broadcasting.auth');

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
    Route::post('/{workflow}/trigger/manual', [WorkflowTriggerController::class, 'manual'])->name('workflows.trigger.manual');
    Route::post('/{workflow}/trigger/form', [WorkflowTriggerController::class, 'form'])->name('workflows.trigger.form');

    // Instance management (list, show, cancel, retry-from-node).
    Route::get('/{workflow}/instances', [WorkflowInstanceController::class, 'index'])->name('workflows.instances.index');
    Route::get('/instances/{instance}', [WorkflowInstanceController::class, 'show'])->name('workflows.instances.show');
    Route::get('/instances/{instance}/failures', [WorkflowInstanceController::class, 'failures'])->name('workflows.instances.failures');
    Route::post('/instances/{instance}/cancel', [WorkflowInstanceController::class, 'cancel'])->name('workflows.instances.cancel');
    Route::post('/instances/{instance}/retry-from-node', [WorkflowInstanceController::class, 'retryFromNode'])->name('workflows.instances.retry');

    // Human-task inbox + submission.
    Route::get('/tasks', [WorkflowTaskController::class, 'index'])->name('workflows.tasks.index');
    Route::post('/tasks/{task}/submit', [WorkflowTaskController::class, 'submit'])->name('workflows.tasks.submit');
});

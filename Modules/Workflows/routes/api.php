<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Modules\Workflows\Http\Controllers\DynamicFlowController;
use Modules\Workflows\Http\Controllers\ManagerReportsController;
use Modules\Workflows\Http\Controllers\MobileTaskCommentsController;
use Modules\Workflows\Http\Controllers\MobileTaskFilesController;
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

// Public, unauthenticated form-trigger link — no auth:api/active.user. Gated at the service layer
// (PublicFormService) to workflows that are published, active, and explicitly marked
// trigger.config.accessLevel === 'public'. Throttled since it's open to the internet.
Route::prefix('v1/public/forms')->middleware(['throttle:30,1'])->group(function (): void {
    Route::get('/{publicToken}', [WorkflowTriggerController::class, 'showForm'])->name('public.forms.show');
    Route::post('/{publicToken}/submit', [WorkflowTriggerController::class, 'submitForm'])->name('public.forms.submit');
});

Route::prefix('v1/workflows')->middleware(['auth:api', 'active.user'])->group(function (): void {
    // Static routes must be declared before /{workflow} to avoid the wildcard swallowing them.
    Route::get('/nodes', [NodeController::class, 'index'])->name('workflows.nodes.index');
    Route::post('/validate', [WorkflowController::class, 'validateDefinition'])->name('workflows.definition.validate');
    Route::get('/templates', [WorkflowController::class, 'templates'])->name('workflows.templates.index');

    // Instance routes with /instances prefix must come before /{workflow}.
    Route::get('/instances/{instance}', [WorkflowInstanceController::class, 'show'])->name('workflows.instances.show');
    Route::get('/instances/{instance}/failures', [WorkflowInstanceController::class, 'failures'])->name('workflows.instances.failures');
    Route::post('/instances/{instance}/cancel', [WorkflowInstanceController::class, 'cancel'])->name('workflows.instances.cancel');
    Route::post('/instances/{instance}/retry-from-node', [WorkflowInstanceController::class, 'retryFromNode'])->name('workflows.instances.retry');

    // Dynamic-flow design routes.
    Route::get('/instances/{instance}/dynamic-flow', [DynamicFlowController::class, 'show'])->name('workflows.instances.dynamic-flow.show');
    Route::post('/instances/{instance}/dynamic-flow/definition', [DynamicFlowController::class, 'storeDefinition'])->name('workflows.instances.dynamic-flow.definition');

    // Human-task inbox + submission.
    Route::get('/tasks/summary', [WorkflowTaskController::class, 'summary'])->name('workflows.tasks.summary');
    Route::get('/tasks', [WorkflowTaskController::class, 'index'])->name('workflows.tasks.index');
    Route::get('/tasks/{task}', [WorkflowTaskController::class, 'show'])->name('workflows.tasks.show');
    Route::patch('/tasks/{task}/draft', [WorkflowTaskController::class, 'saveDraft'])->name('workflows.tasks.draft');
    Route::post('/tasks/{task}/submit', [WorkflowTaskController::class, 'submit'])->name('workflows.tasks.submit');
    Route::get('/tasks/{task}/files', [MobileTaskFilesController::class, 'index'])->name('workflows.tasks.files.index');
    Route::post('/tasks/{task}/files', [MobileTaskFilesController::class, 'store'])->name('workflows.tasks.files.store');
    Route::delete('/tasks/{task}/files/{attachment}', [MobileTaskFilesController::class, 'destroy'])->name('workflows.tasks.files.destroy');
    Route::get('/tasks/{task}/comments', [MobileTaskCommentsController::class, 'index'])->name('workflows.tasks.comments.index');
    Route::post('/tasks/{task}/comments', [MobileTaskCommentsController::class, 'store'])->name('workflows.tasks.comments.store');
    Route::delete('/tasks/{task}/comments/{comment}', [MobileTaskCommentsController::class, 'destroy'])->name('workflows.tasks.comments.destroy');

    // Manager Reports — Team Performance & Workflow Analytics.
    Route::prefix('reports')->group(function (): void {
        Route::get('/team-performance', [ManagerReportsController::class, 'teamPerformance'])->name('workflows.reports.team-performance');
        Route::get('/team-performance/export', [ManagerReportsController::class, 'exportTeamPerformance'])->name('workflows.reports.team-performance.export');
        Route::get('/workflow-analytics', [ManagerReportsController::class, 'workflowAnalytics'])->name('workflows.reports.workflow-analytics');
        Route::get('/workflow-analytics/export', [ManagerReportsController::class, 'exportWorkflowAnalytics'])->name('workflows.reports.workflow-analytics.export');
    });

    // Collection routes.
    Route::get('/', [WorkflowController::class, 'index'])->name('workflows.index');
    Route::post('/', [WorkflowController::class, 'store'])->name('workflows.store');

    // Parameterised routes — /{workflow} wildcard last.
    Route::get('/{workflow}', [WorkflowController::class, 'show'])->name('workflows.show');
    Route::patch('/{workflow}/draft', [WorkflowController::class, 'updateDraft'])->name('workflows.draft.update');
    Route::post('/{workflow}/publish', [WorkflowController::class, 'publish'])->name('workflows.publish');
    Route::get('/{workflow}/versions', [WorkflowController::class, 'versions'])->name('workflows.versions.index');
    Route::patch('/{workflow}/status', [WorkflowController::class, 'updateStatus'])->name('workflows.status.update');
    Route::delete('/{workflow}', [WorkflowController::class, 'destroy'])->name('workflows.destroy');
    Route::delete('/{workflow}/purge', [WorkflowController::class, 'purge'])->name('workflows.purge');
    Route::post('/{workflow}/trigger/webhook', [WorkflowTriggerController::class, 'triggerWebhook'])->name('workflows.trigger.webhook');
    Route::post('/{workflow}/trigger/manual', [WorkflowTriggerController::class, 'manual'])->name('workflows.trigger.manual');
    Route::get('/{workflow}/instances', [WorkflowInstanceController::class, 'index'])->name('workflows.instances.index');
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\Customers\Http\Controllers\CustomerController;
use Modules\Customers\Http\Controllers\CustomerFieldController;
use Modules\Customers\Http\Controllers\CustomerSettingsController;

// All Customer routes are gated to business_owner/manager/admin only (no space after the
// comma — KnowledgeBase has a documented bug where a leading space silently locks out
// Managers; not repeating it here). Customer records carry more PII than a KnowledgeBase
// document, so unlike KnowledgeBase, reads are restricted too, not just writes.
Route::prefix('v1/customers')->middleware(['auth:api', 'active.user', 'role:business_owner,manager,admin'])->group(function (): void {
    Route::get('/fields', [CustomerFieldController::class, 'index'])->name('customers.fields.index');
    Route::post('/fields', [CustomerFieldController::class, 'store'])->name('customers.fields.store');
    Route::put('/fields/{customerField}', [CustomerFieldController::class, 'update'])->name('customers.fields.update');
    Route::delete('/fields/{customerField}', [CustomerFieldController::class, 'destroy'])->name('customers.fields.destroy');

    Route::get('/settings', [CustomerSettingsController::class, 'show'])->name('customers.settings.show');
    Route::put('/settings', [CustomerSettingsController::class, 'update'])->name('customers.settings.update');

    Route::get('/', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/{id}', [CustomerController::class, 'show'])->name('customers.show');
    Route::put('/{id}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/{id}', [CustomerController::class, 'destroy'])->name('customers.destroy');

    // Workflow instances linked to this customer via the "customer context" trigger feature.
    // Same paginated response shape as GET /api/v1/workflows/{workflow}/instances.
    Route::get('/{id}/instances', [CustomerController::class, 'instances'])->name('customers.instances.index');
});

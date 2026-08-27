<?php

use Illuminate\Support\Facades\Route;
use Modules\KnowledgeBase\Http\Controllers\DocumentController;
use Modules\KnowledgeBase\Http\Controllers\DocumentTypeController;
use Modules\KnowledgeBase\Http\Controllers\MobileDocumentController;
use Modules\KnowledgeBase\Http\Controllers\TagController;

Route::prefix('v1')->middleware(['auth:api', 'active.user'])->group(function () {
    Route::get('mobile/documents', [MobileDocumentController::class, 'index'])->name('mobile.documents.index');
    Route::get('document-types', [DocumentTypeController::class, 'index'])->name('document-types.index');
    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [TagController::class, 'store'])->name('tags.store')->middleware('role:business_owner, manager');

    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store')->middleware('role:business_owner, manager');
    Route::get('documents/{id}', [DocumentController::class, 'show'])->name('documents.show');
    Route::put('documents/{id}', [DocumentController::class, 'update'])->name('documents.update')->middleware('role:business_owner, manager');
    Route::get('documents/{id}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::get('documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::patch('documents/{id}/activity/{is_active}', [DocumentController::class, 'setActive'])->name('documents.setActive')->middleware('role:business_owner, manager');
    Route::delete('documents/{id}', [DocumentController::class, 'destroy'])->name('documents.destroy')->middleware('role:business_owner, manager');
});

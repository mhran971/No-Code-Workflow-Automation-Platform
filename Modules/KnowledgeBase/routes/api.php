<?php

use Illuminate\Support\Facades\Route;
use Modules\KnowledgeBase\Http\Controllers\DocumentTypeController;
use Modules\KnowledgeBase\Http\Controllers\TagController;

Route::prefix('v1')->middleware('auth:api')->group(function () {
    Route::get('document-types', [DocumentTypeController::class, 'index'])->name('document-types.index');
    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
});

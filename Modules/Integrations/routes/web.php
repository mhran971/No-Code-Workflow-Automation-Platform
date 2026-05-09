<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('integrations', fn () => view('integrations::index'))->name('integrations.index');
});

<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('testmodule')->group(function () {
    Route::get('/', function () {
        return inertia('TestModule/Index');
    })->name('TestModule.index');
});

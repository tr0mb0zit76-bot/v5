<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    Route::get('/dashboard', function () {
        return inertia('Dashboard');
    })->middleware(['auth'])->name('dashboard');
});

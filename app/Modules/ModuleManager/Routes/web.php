<?php

use Illuminate\Support\Facades\Route;
use App\Modules\ModuleManager\Http\Controllers\ModuleManagerController;

Route::middleware(['web', 'auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/modules', [ModuleManagerController::class, 'index'])->name('admin.modules');
});
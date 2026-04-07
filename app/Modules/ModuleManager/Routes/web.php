<?php

use App\Modules\ModuleManager\Http\Controllers\ModuleManagerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/modules', [ModuleManagerController::class, 'index'])->name('admin.modules');
});

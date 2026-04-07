<?php

use App\Modules\ModuleManager\Http\Controllers\ModuleManagerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('api')->group(function () {
    Route::get('/modules', [ModuleManagerController::class, 'getModules']);
    Route::post('/modules/{module}/toggle', [ModuleManagerController::class, 'toggle']);
    Route::post('/modules/install', [ModuleManagerController::class, 'install']);
    Route::delete('/modules/{module}', [ModuleManagerController::class, 'uninstall']);
});

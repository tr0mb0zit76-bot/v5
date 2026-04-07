<?php

namespace App\Modules\ModuleManager\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModuleManagerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Регистрируем маршруты
        $this->loadRoutes();

        // Регистрируем представления если есть
        $this->loadViews();
    }

    public function register(): void
    {
        // Регистрируем конфигурацию
        $this->mergeConfigFrom(
            __DIR__.'/../Config/module.php',
            'module-manager'
        );
    }

    protected function loadRoutes(): void
    {
        // Загружаем web маршруты
        Route::middleware('web')
            ->group(__DIR__.'/../Routes/web.php');

        // Загружаем api маршруты
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../Routes/api.php');
    }

    protected function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'module-manager');
    }
}

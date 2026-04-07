<?php

namespace App\Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        // Загружаем миграции
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        // Загружаем маршруты
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }

    /**
     * Register any module services.
     */
    public function register(): void
    {
        // Регистрируем конфиг
        $this->mergeConfigFrom(
            __DIR__.'/../Config/module.php',
            'core'
        );
    }
}

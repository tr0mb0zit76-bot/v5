<?php

namespace App\Modules\TestModule\Providers;

use Illuminate\Support\ServiceProvider;

class TestModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Config/module.php',
            'testmodule'
        );
    }
}

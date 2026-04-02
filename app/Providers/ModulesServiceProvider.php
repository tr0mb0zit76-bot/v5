<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerEnabledModules();
    }
    
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadModulesMigrations();
        $this->loadModulesRoutes();
    }
    
    /**
     * Register all enabled modules from database
     */
    protected function registerEnabledModules(): void
    {
        // Ждем, пока загрузится база данных
        if (!$this->app->runningInConsole() && $this->isDatabaseReady()) {
            try {
                $modules = DB::table('modules')
                    ->where('enabled', true)
                    ->orderBy('order')
                    ->get();
                
                foreach ($modules as $module) {
                    $this->registerModuleProvider($module->name);
                }
            } catch (\Exception $e) {
                // Таблица modules еще не создана, игнорируем
            }
        }
    }
    
    /**
     * Register a single module provider
     */
    protected function registerModuleProvider(string $moduleName): void
    {
        $providerClass = "App\\Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
        
        if (class_exists($providerClass)) {
            $this->app->register($providerClass);
        }
    }
    
    /**
     * Load migrations from all modules
     */
    protected function loadModulesMigrations(): void
    {
        $modulesPath = app_path('Modules');
        
        if (!File::exists($modulesPath)) {
            return;
        }
        
        $modules = File::directories($modulesPath);
        
        foreach ($modules as $module) {
            $migrationsPath = $module . '/Database/Migrations';
            
            if (File::exists($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }
        }
    }
    
    /**
     * Load routes from all modules
     */
    protected function loadModulesRoutes(): void
    {
        $modulesPath = app_path('Modules');
        
        if (!File::exists($modulesPath)) {
            return;
        }
        
        $modules = File::directories($modulesPath);
        
        foreach ($modules as $module) {
            // Загружаем web routes
            $webRoutes = $module . '/Routes/web.php';
            if (File::exists($webRoutes)) {
                $this->loadRoutesFrom($webRoutes);
            }
            
            // Загружаем api routes
            $apiRoutes = $module . '/Routes/api.php';
            if (File::exists($apiRoutes)) {
                $this->loadRoutesFrom($apiRoutes);
            }
        }
    }
    
    /**
     * Check if database is ready for queries
     */
    protected function isDatabaseReady(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
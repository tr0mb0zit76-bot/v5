<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ModuleManage extends Command
{
    protected $signature = 'module:manage {action : create|list|delete} {name? : Module name}';

    protected $description = 'Manage Laravel modules';

    protected $files;

    protected $moduleStructure = [
        'Config',
        'Database/Migrations',
        'Http/Controllers',
        'Models',
        'Providers',
        'Routes',
    ];

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle()
    {
        $action = $this->argument('action');
        $name = $this->argument('name');

        match ($action) {
            'create' => $this->createModule($name),
            'list' => $this->listModules(),
            'delete' => $this->deleteModule($name),
            default => $this->error("Action '{$action}' not recognized"),
        };
    }

    protected function createModule($name)
    {
        if (! $name) {
            $this->error('Module name is required');

            return;
        }

        $modulePath = app_path("Modules/{$name}");

        if ($this->files->exists($modulePath)) {
            $this->error("Module {$name} already exists");

            return;
        }

        $this->info("Creating module: {$name}");

        // Создаем структуру
        foreach ($this->moduleStructure as $structure) {
            $this->files->makeDirectory($modulePath.'/'.$structure, 0755, true);
        }

        // Создаем конфиг
        $this->createModuleConfig($name);

        // Создаем ServiceProvider
        $this->createServiceProvider($name);

        // Создаем routes
        $this->createRoutes($name);

        $this->info("Module {$name} created successfully!");
        $this->info("Don't forget to add it to the database via: php artisan db:seed or manually");
    }

    protected function createModuleConfig($name)
    {
        $path = app_path("Modules/{$name}/Config/module.php");
        $content = "<?php\n\nreturn [\n    'name' => '{$name}',\n    'version' => '1.0.0',\n    'enabled' => true,\n    'order' => 0,\n    'dependencies' => [],\n];\n";

        $this->files->put($path, $content);
        $this->line('  Created: Config/module.php');
    }

    protected function createServiceProvider($name)
    {
        $path = app_path("Modules/{$name}/Providers/{$name}ServiceProvider.php");
        $content = "<?php\n\nnamespace App\\Modules\\{$name}\\Providers;\n\nuse Illuminate\Support\ServiceProvider;\n\nclass {$name}ServiceProvider extends ServiceProvider\n{\n    public function boot(): void\n    {\n        \$this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');\n        \$this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');\n    }\n\n    public function register(): void\n    {\n        \$this->mergeConfigFrom(\n            __DIR__ . '/../Config/module.php',\n            '".strtolower($name)."'\n        );\n    }\n}\n";

        $this->files->put($path, $content);
        $this->line("  Created: Providers/{$name}ServiceProvider.php");
    }

    protected function createRoutes($name)
    {
        $webPath = app_path("Modules/{$name}/Routes/web.php");
        $webContent = "<?php\n\nuse Illuminate\Support\Facades\Route;\n\nRoute::middleware(['web', 'auth'])->prefix('".strtolower($name)."')->group(function () {\n    Route::get('/', function () {\n        return inertia('".$name."/Index');\n    })->name('{$name}.index');\n});\n";

        $this->files->put($webPath, $webContent);
        $this->line('  Created: Routes/web.php');

        $apiPath = app_path("Modules/{$name}/Routes/api.php");
        $apiContent = "<?php\n\nuse Illuminate\Support\Facades\Route;\n\nRoute::prefix('api')->middleware('api')->group(function () {\n    // API routes for {$name} module\n});\n";

        $this->files->put($apiPath, $apiContent);
        $this->line('  Created: Routes/api.php');
    }

    protected function listModules()
    {
        $modulesPath = app_path('Modules');

        if (! $this->files->exists($modulesPath)) {
            $this->info('No modules found');

            return;
        }

        $modules = $this->files->directories($modulesPath);

        if (empty($modules)) {
            $this->info('No modules found');

            return;
        }

        $this->info("\nInstalled modules:\n");

        foreach ($modules as $module) {
            $name = basename($module);
            $configPath = $module.'/Config/module.php';

            if ($this->files->exists($configPath)) {
                $config = require $configPath;
                $status = $config['enabled'] ? '✓' : '✗';
                $this->line("  {$status} {$name} v{$config['version']}");
            } else {
                $this->line("  ? {$name} (no config)");
            }
        }
    }

    protected function deleteModule($name)
    {
        if (! $name) {
            $this->error('Module name is required');

            return;
        }

        if ($name === 'Core') {
            $this->error('Cannot delete Core module');

            return;
        }

        $modulePath = app_path("Modules/{$name}");

        if (! $this->files->exists($modulePath)) {
            $this->error("Module {$name} does not exist");

            return;
        }

        if ($this->confirm("Are you sure you want to delete {$name} module?")) {
            $this->files->deleteDirectory($modulePath);
            $this->info("Module {$name} deleted");
        }
    }
}

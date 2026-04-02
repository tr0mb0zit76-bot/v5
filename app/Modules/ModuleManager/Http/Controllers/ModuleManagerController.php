<?php

namespace App\Modules\ModuleManager\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class ModuleManagerController extends Controller
{
    /**
     * Show module manager page
     */
    public function index()
    {
        // Проверяем, что пользователь авторизован и имеет права
        if (!auth()->check()) {
            return redirect()->route('login');
        }
        
        return inertia('ModuleManager/Index');
    }
	
	/**
	 * Update module order
	 */
	public function updateOrder(Request $request, Module $module)
	{
		$request->validate([
			'order' => 'required|integer'
		]);
		
		$module->order = $request->order;
		$module->save();
		
		return response()->json(['success' => true]);
	}
    
    /**
     * Get all modules
     */
    public function getModules()
    {
        try {
            $modules = Module::orderBy('order')->get();
            
            // Добавляем информацию о физическом существовании модуля
            foreach ($modules as $module) {
                $module->exists_physically = File::exists(app_path("Modules/{$module->name}"));
            }
            
            return response()->json($modules);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Toggle module status
     */
    public function toggle(Module $module)
    {
        try {
            // Меняем статус
            $module->enabled = !$module->enabled;
            $module->save();
            
            // Обновляем конфиг модуля
            $this->updateModuleConfig($module->name, ['enabled' => $module->enabled]);
            
            // Очищаем кеш
            Artisan::call('cache:clear');
            
            return response()->json([
                'success' => true,
                'message' => "Module {$module->name} " . ($module->enabled ? 'enabled' : 'disabled'),
                'enabled' => $module->enabled
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Install new module
     */
    public function install(Request $request)
    {
        try {
            $name = $request->input('name');
            
            if (!$name) {
                return response()->json(['error' => 'Module name is required'], 400);
            }
            
            // Проверяем, не существует ли уже такой модуль
            if (Module::where('name', $name)->exists()) {
                return response()->json(['error' => 'Module already exists'], 400);
            }
            
            // Создаем модуль через Artisan
            Artisan::call('module:manage', [
                'action' => 'create',
                'name' => $name
            ]);
            
            // Добавляем в базу данных
            $module = Module::create([
                'name' => $name,
                'slug' => strtolower($name),
                'enabled' => true,
                'order' => Module::count() + 1,
                'version' => '1.0.0'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Module {$name} installed successfully",
                'module' => $module
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Uninstall module
     */
    public function uninstall(Module $module)
    {
        try {
            if ($module->name === 'Core') {
                return response()->json(['error' => 'Cannot uninstall Core module'], 403);
            }
            
            // Удаляем модуль через Artisan
            Artisan::call('module:manage', [
                'action' => 'delete',
                'name' => $module->name,
                '--force' => true
            ]);
            
            // Удаляем из базы данных
            $module->delete();
            
            // Очищаем кеш
            Artisan::call('cache:clear');
            
            return response()->json([
                'success' => true,
                'message' => "Module {$module->name} uninstalled"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Update module config file
     */
    protected function updateModuleConfig(string $moduleName, array $data)
    {
        $configPath = app_path("Modules/{$moduleName}/Config/module.php");
        
        if (File::exists($configPath)) {
            $config = require $configPath;
            $config = array_merge($config, $data);
            
            $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
            File::put($configPath, $content);
        }
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;

class ModulesTableSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'name' => 'Core',
                'slug' => 'core',
                'version' => '1.0.0',
                'enabled' => true,
                'order' => 1,
            ],
            [
                'name' => 'ModuleManager',
                'slug' => 'module-manager',
                'version' => '1.0.0',
                'enabled' => true,
                'order' => 2,
            ],
        ];
        
        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['slug' => $module['slug']],
                $module
            );
        }
        
        $this->command->info('Modules seeded successfully!');
    }
}
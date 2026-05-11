<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModulesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        if (DB::table('modules')->exists()) {
            return;
        }

        $enabledColumn = Schema::hasColumn('modules', 'is_enabled') ? 'is_enabled' : 'enabled';

        $now = now();
        $rows = [
            [
                'name' => 'Core',
                'slug' => 'core',
                'version' => '1.0.0',
                $enabledColumn => true,
                'order' => 1,
                'dependencies' => json_encode([]),
                'settings' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'ModuleManager',
                'slug' => 'module-manager',
                'version' => '1.0.0',
                $enabledColumn => true,
                'order' => 2,
                'dependencies' => json_encode(['Core']),
                'settings' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('modules')->insert($rows);
    }
}

<?php

use App\Support\RoleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'visibility_areas')) {
            return;
        }

        $allAreas = json_encode(RoleAccess::visibilityAreaKeys(), JSON_UNESCAPED_UNICODE);

        DB::table('roles')
            ->where('name', 'admin')
            ->update([
                'visibility_areas' => $allAreas,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Намеренно без отката: предыдущий урезанный список не восстанавливаем.
    }
};

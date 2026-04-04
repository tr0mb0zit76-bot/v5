<?php

use App\Support\RoleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || Schema::hasColumn('roles', 'visibility_areas')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->json('visibility_areas')->nullable()->after('permissions');
        });

        DB::table('roles')
            ->select('id', 'name')
            ->orderBy('id')
            ->get()
            ->each(function (object $role): void {
                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'visibility_areas' => json_encode(RoleAccess::defaultVisibilityAreas($role->name), JSON_THROW_ON_ERROR),
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'visibility_areas')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('visibility_areas');
        });
    }
};

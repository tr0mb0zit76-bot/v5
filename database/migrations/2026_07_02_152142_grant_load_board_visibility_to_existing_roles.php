<?php

use App\Models\Role;
use App\Support\RoleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'visibility_areas')) {
            return;
        }

        Role::query()->whereIn('name', ['admin', 'supervisor', 'manager', 'dispatcher'])->each(function (Role $role): void {
            $areas = is_array($role->visibility_areas) ? $role->visibility_areas : [];

            if (RoleAccess::hasVisibilityArea($areas, 'load_board')) {
                return;
            }

            $areas[] = 'load_board';
            $role->visibility_areas = array_values(array_unique($areas));
            $role->save();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'visibility_areas')) {
            return;
        }

        Role::query()->whereIn('name', ['supervisor', 'manager', 'dispatcher'])->each(function (Role $role): void {
            $areas = is_array($role->visibility_areas) ? $role->visibility_areas : [];
            $filtered = array_values(array_filter(
                $areas,
                static fn (string $area): bool => $area !== 'load_board',
            ));

            if ($filtered !== $areas) {
                $role->visibility_areas = $filtered;
                $role->save();
            }
        });
    }
};

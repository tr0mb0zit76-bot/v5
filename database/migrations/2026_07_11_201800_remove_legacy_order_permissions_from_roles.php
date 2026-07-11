<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const LEGACY_PERMISSIONS = [
        'view_orders',
        'create_orders',
        'edit_orders',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'permissions')) {
            return;
        }

        Role::query()->each(function (Role $role): void {
            $permissions = is_array($role->permissions) ? $role->permissions : [];

            if ($permissions === []) {
                return;
            }

            $filtered = array_values(array_filter(
                $permissions,
                static fn (mixed $permission): bool => is_string($permission)
                    && $permission !== ''
                    && ! in_array($permission, self::LEGACY_PERMISSIONS, true),
            ));

            if ($filtered === $permissions) {
                return;
            }

            $role->permissions = $filtered;
            $role->save();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Legacy permissions were unused at runtime; no restore.
    }
};

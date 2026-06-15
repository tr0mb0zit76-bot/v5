<?php

use App\Models\Role;
use App\Support\RoleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'visibility_areas')) {
            return;
        }

        Role::query()->where('name', 'accountant')->each(function (Role $role): void {
            $areas = is_array($role->visibility_areas) ? $role->visibility_areas : [];

            if (RoleAccess::hasVisibilityArea($areas, 'finance_payment_reconcile')) {
                return;
            }

            $areas[] = 'finance_payment_reconcile';
            $role->visibility_areas = array_values(array_unique($areas));
            $role->save();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'visibility_areas')) {
            return;
        }

        Role::query()->where('name', 'accountant')->each(function (Role $role): void {
            $areas = is_array($role->visibility_areas) ? $role->visibility_areas : [];
            $filtered = array_values(array_filter(
                $areas,
                static fn (string $area): bool => $area !== 'finance_payment_reconcile',
            ));

            if ($filtered !== $areas) {
                $role->visibility_areas = $filtered;
                $role->save();
            }
        });
    }
};

<?php

use App\Models\Role;
use App\Support\ContractorTableColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'columns_config')) {
            return;
        }

        $catalogByField = collect(ContractorTableColumns::options())->keyBy('field');

        Role::query()->each(function (Role $role) use ($catalogByField): void {
            $columnsConfig = is_array($role->columns_config) ? $role->columns_config : [];
            $contractorsPreset = $columnsConfig['contractors'] ?? null;

            if (! is_array($contractorsPreset)) {
                return;
            }

            $updatedPreset = ContractorTableColumns::mergePresetWithCatalog($contractorsPreset);
            $ownerColumn = $catalogByField->get('owner_name');
            $found = false;

            foreach ($updatedPreset as &$column) {
                if (($column['colId'] ?? null) !== 'owner_name') {
                    continue;
                }

                $column['hide'] = false;
                $found = true;
                break;
            }

            unset($column);

            if (! $found && is_array($ownerColumn)) {
                $nextOrder = collect($updatedPreset)->max(fn (array $column): int => (int) ($column['order'] ?? 0)) + 1;

                $updatedPreset[] = [
                    'colId' => 'owner_name',
                    'hide' => false,
                    'width' => (int) ($ownerColumn['width'] ?? 180),
                    'order' => $nextOrder,
                ];
            }

            $columnsConfig['contractors'] = $updatedPreset;
            $role->forceFill(['columns_config' => $columnsConfig])->saveQuietly();
        });
    }

    public function down(): void
    {
        // Намеренно без отката: скрытие колонки у существующих ролей не восстанавливаем.
    }
};

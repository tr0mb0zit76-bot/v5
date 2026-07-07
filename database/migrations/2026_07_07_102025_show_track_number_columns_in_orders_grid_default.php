<?php

use App\Models\Role;
use App\Support\OrderTableColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TRACK_NUMBER_COLUMNS = [
        'track_number_customer',
        'track_number_carrier',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'columns_config')) {
            return;
        }

        $catalogByField = collect(OrderTableColumns::options())->keyBy('field');

        Role::query()->each(function (Role $role) use ($catalogByField): void {
            $columnsConfig = is_array($role->columns_config) ? $role->columns_config : [];
            $ordersPreset = is_array($columnsConfig['orders'] ?? null)
                ? $columnsConfig['orders']
                : OrderTableColumns::defaultState((string) $role->name);

            $updatedPreset = OrderTableColumns::mergePresetWithCatalog($ordersPreset);

            foreach (self::TRACK_NUMBER_COLUMNS as $trackField) {
                $found = false;

                foreach ($updatedPreset as &$column) {
                    if (($column['colId'] ?? null) !== $trackField) {
                        continue;
                    }

                    $column['hide'] = false;
                    $found = true;
                    break;
                }

                unset($column);

                if ($found) {
                    continue;
                }

                $trackColumn = $catalogByField->get($trackField);

                if (! is_array($trackColumn)) {
                    continue;
                }

                $nextOrder = collect($updatedPreset)->max(fn (array $column): int => (int) ($column['order'] ?? 0)) + 1;

                $updatedPreset[] = [
                    'colId' => $trackField,
                    'hide' => false,
                    'width' => (int) ($trackColumn['width'] ?? 160),
                    'order' => $nextOrder,
                ];
            }

            $columnsConfig['orders'] = $updatedPreset;
            $role->forceFill(['columns_config' => $columnsConfig])->saveQuietly();
        });
    }

    public function down(): void
    {
        // Намеренно без отката.
    }
};

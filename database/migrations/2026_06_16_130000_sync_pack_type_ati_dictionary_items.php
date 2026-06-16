<?php

use App\Models\AtiDictionaryItem;
use App\Support\AtiDictionaryOptionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ati_dictionary_items')) {
            return;
        }

        foreach (AtiDictionaryOptionCatalog::fallbackPackageTypeOptions() as $option) {
            AtiDictionaryItem::query()->updateOrCreate(
                [
                    'dictionary' => 'pack_type',
                    'code' => $option['code'],
                ],
                [
                    'ati_id' => $option['value'],
                    'label' => $option['label'],
                    'is_active' => true,
                ],
            );
        }
    }

    public function down(): void
    {
        // Не откатываем: в БД могли быть и другие типы упаковки из АТИ.
    }
};

<?php

use App\Models\AtiDictionaryItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ati_dictionary_items')) {
            return;
        }

        AtiDictionaryItem::query()->firstOrCreate(
            [
                'dictionary' => 'pack_type',
                'code' => 'barrel',
            ],
            [
                'ati_id' => 6,
                'label' => 'Бочки',
                'is_active' => true,
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('ati_dictionary_items')) {
            return;
        }

        AtiDictionaryItem::query()
            ->where('dictionary', 'pack_type')
            ->where('code', 'barrel')
            ->delete();
    }
};

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

        foreach ([
            ['code' => 'pallet', 'ati_id' => 1, 'label' => 'Паллета'],
            ['code' => 'box', 'ati_id' => 2, 'label' => 'Короб'],
            ['code' => 'crate', 'ati_id' => 3, 'label' => 'Ящик'],
            ['code' => 'roll', 'ati_id' => 4, 'label' => 'Рулон'],
            ['code' => 'bag', 'ati_id' => 5, 'label' => 'Мешок'],
            ['code' => 'barrel', 'ati_id' => 6, 'label' => 'Бочки'],
        ] as $option) {
            AtiDictionaryItem::query()->firstOrCreate(
                [
                    'dictionary' => 'pack_type',
                    'code' => $option['code'],
                ],
                [
                    'ati_id' => $option['ati_id'],
                    'label' => $option['label'],
                    'is_active' => true,
                ],
            );
        }
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

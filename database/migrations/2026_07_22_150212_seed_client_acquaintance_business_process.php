<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_processes') || ! Schema::hasTable('business_process_stages')) {
            return;
        }

        if (DB::table('business_processes')->where('slug', 'client-acquaintance')->exists()) {
            return;
        }

        $now = now();

        $processId = DB::table('business_processes')->insertGetId([
            'name' => 'Знакомство с клиентом',
            'slug' => 'client-acquaintance',
            'description' => 'Холодный и тёплый вход: выход на ЛПР, портрет компании, договорённость о следующем шаге. Успех — конверсия в лид «Получение деталей по перевозке», не заказ.',
            'is_active' => true,
            'sort_order' => 15,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stages = [
            [
                'name' => 'Выход на ЛПР',
                'sequence' => 10,
                'duration_days' => 3,
                'is_terminal' => false,
                'terminal_outcome' => null,
            ],
            [
                'name' => 'Диагностика и портрет',
                'sequence' => 20,
                'duration_days' => 7,
                'is_terminal' => false,
                'terminal_outcome' => null,
            ],
            [
                'name' => 'Следующее касание',
                'sequence' => 30,
                'duration_days' => 14,
                'is_terminal' => false,
                'terminal_outcome' => null,
            ],
            [
                'name' => 'Отказ',
                'sequence' => 40,
                'duration_days' => 0,
                'is_terminal' => true,
                'terminal_outcome' => 'lost',
            ],
            [
                'name' => 'Готов к перевозке',
                'sequence' => 50,
                'duration_days' => 0,
                'is_terminal' => true,
                'terminal_outcome' => 'won',
            ],
        ];

        foreach ($stages as $stage) {
            $row = [
                'business_process_id' => $processId,
                'name' => $stage['name'],
                'description' => null,
                'sequence' => $stage['sequence'],
                'duration_days' => $stage['duration_days'],
                'is_terminal' => $stage['is_terminal'],
                'terminal_outcome' => $stage['terminal_outcome'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('business_process_stages', 'auto_create_task')) {
                $row['auto_create_task'] = false;
            }

            DB::table('business_process_stages')->insert($row);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_processes')) {
            return;
        }

        $processId = DB::table('business_processes')->where('slug', 'client-acquaintance')->value('id');
        if ($processId === null) {
            return;
        }

        if (Schema::hasTable('business_process_stages')) {
            DB::table('business_process_stages')->where('business_process_id', $processId)->delete();
        }

        DB::table('business_processes')->where('id', $processId)->delete();
    }
};

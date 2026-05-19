<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_processes')) {
            Schema::create('business_processes', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('business_process_stages')) {
            Schema::create('business_process_stages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_process_id')->constrained('business_processes')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('sequence')->default(0);
                /** Норматив: сколько рабочих дней на этап (0 = без отдельного SLA этапа). */
                $table->unsignedSmallInteger('duration_days')->default(0);
                $table->boolean('is_terminal')->default(false);
                /** won | lost | neutral — для финальных этапов. */
                $table->string('terminal_outcome', 20)->nullable();
                $table->timestamps();

                $table->index(['business_process_id', 'sequence']);
            });
        }

        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if (! Schema::hasColumn('leads', 'business_process_id')) {
                    $table->foreignId('business_process_id')->nullable()->after('status')->constrained('business_processes')->nullOnDelete();
                }
                if (! Schema::hasColumn('leads', 'business_process_stage_id')) {
                    $table->foreignId('business_process_stage_id')->nullable()->after('business_process_id')->constrained('business_process_stages')->nullOnDelete();
                }
                if (! Schema::hasColumn('leads', 'process_started_at')) {
                    $table->timestamp('process_started_at')->nullable()->after('business_process_stage_id');
                }
                if (! Schema::hasColumn('leads', 'stage_entered_at')) {
                    $table->timestamp('stage_entered_at')->nullable()->after('process_started_at');
                }
                if (! Schema::hasColumn('leads', 'stage_due_at')) {
                    $table->timestamp('stage_due_at')->nullable()->after('stage_entered_at');
                }
            });
        }

        if (! Schema::hasTable('lead_process_stage_logs')) {
            Schema::create('lead_process_stage_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->foreignId('business_process_stage_id')->constrained('business_process_stages')->cascadeOnDelete();
                $table->timestamp('entered_at');
                $table->timestamp('exited_at')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['lead_id', 'entered_at']);
            });
        }

        $this->seedDefaultProcesses();
    }

    public function down(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if (Schema::hasColumn('leads', 'business_process_stage_id')) {
                    $table->dropConstrainedForeignId('business_process_stage_id');
                }
                if (Schema::hasColumn('leads', 'business_process_id')) {
                    $table->dropConstrainedForeignId('business_process_id');
                }
                foreach (['stage_due_at', 'stage_entered_at', 'process_started_at'] as $column) {
                    if (Schema::hasColumn('leads', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('lead_process_stage_logs');
        Schema::dropIfExists('business_process_stages');
        Schema::dropIfExists('business_processes');
    }

    private function seedDefaultProcesses(): void
    {
        if (! Schema::hasTable('business_processes') || DB::table('business_processes')->exists()) {
            return;
        }

        $now = now();

        $transportId = DB::table('business_processes')->insertGetId([
            'name' => 'Получение деталей по перевозке',
            'slug' => 'transport-intake',
            'description' => 'Воронка от запроса до расчёта, согласования цены и решения по сделке.',
            'is_active' => true,
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $transportStages = [
            ['name' => 'Получение деталей по перевозке', 'duration_days' => 2, 'sequence' => 10],
            ['name' => 'Расчёт цены', 'duration_days' => 3, 'sequence' => 20],
            ['name' => 'Согласование цены', 'duration_days' => 5, 'sequence' => 30],
            ['name' => 'Отказ', 'duration_days' => 0, 'sequence' => 40, 'is_terminal' => true, 'terminal_outcome' => 'lost'],
            ['name' => 'Подписание', 'duration_days' => 0, 'sequence' => 50, 'is_terminal' => true, 'terminal_outcome' => 'won'],
        ];

        foreach ($transportStages as $stage) {
            DB::table('business_process_stages')->insert([
                'business_process_id' => $transportId,
                'name' => $stage['name'],
                'description' => null,
                'sequence' => $stage['sequence'],
                'duration_days' => $stage['duration_days'],
                'is_terminal' => $stage['is_terminal'] ?? false,
                'terminal_outcome' => $stage['terminal_outcome'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $contractId = DB::table('business_processes')->insertGetId([
            'name' => 'Подписание контракта',
            'slug' => 'contract-signing',
            'description' => 'Сбор документов, согласование и закрытие разногласий.',
            'is_active' => true,
            'sort_order' => 20,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $contractStages = [
            ['name' => 'Сбор документов', 'duration_days' => 3, 'sequence' => 10],
            ['name' => 'Согласование', 'duration_days' => 5, 'sequence' => 20],
            ['name' => 'Протокол разногласий', 'duration_days' => 7, 'sequence' => 30],
            ['name' => 'Подписан', 'duration_days' => 0, 'sequence' => 40, 'is_terminal' => true, 'terminal_outcome' => 'won'],
        ];

        foreach ($contractStages as $stage) {
            DB::table('business_process_stages')->insert([
                'business_process_id' => $contractId,
                'name' => $stage['name'],
                'description' => null,
                'sequence' => $stage['sequence'],
                'duration_days' => $stage['duration_days'],
                'is_terminal' => $stage['is_terminal'] ?? false,
                'terminal_outcome' => $stage['terminal_outcome'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};

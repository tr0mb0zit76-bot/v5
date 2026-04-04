<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractor_activity_types')) {
            Schema::create('contractor_activity_types', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contractors') || ! Schema::hasColumn('contractors', 'activity_types')) {
            return;
        }

        $existingValues = DB::table('contractors')
            ->whereNotNull('activity_types')
            ->pluck('activity_types')
            ->flatMap(function (mixed $value): array {
                if (is_array($value)) {
                    return $value;
                }

                if (is_string($value)) {
                    $decoded = json_decode($value, true);

                    return is_array($decoded) ? $decoded : [];
                }

                return [];
            })
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        foreach ($existingValues as $name) {
            DB::table('contractor_activity_types')->updateOrInsert(
                ['name' => $name],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_activity_types');
    }
};

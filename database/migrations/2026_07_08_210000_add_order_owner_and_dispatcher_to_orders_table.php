<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('order_owner_id')
                ->nullable()
                ->after('manager_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('dispatcher_id')
                ->nullable()
                ->after('order_owner_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('orders')
            ->whereNull('order_owner_id')
            ->update(['order_owner_id' => DB::raw('manager_id')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dispatcher_id');
            $table->dropConstrainedForeignId('order_owner_id');
        });
    }
};

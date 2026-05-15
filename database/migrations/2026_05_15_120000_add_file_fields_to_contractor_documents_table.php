<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractor_documents')) {
            return;
        }

        Schema::table('contractor_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('contractor_documents', 'original_name')) {
                $table->string('original_name')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('contractor_documents', 'file_path')) {
                $table->string('file_path')->nullable()->after('original_name');
            }

            if (! Schema::hasColumn('contractor_documents', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('file_path');
            }

            if (! Schema::hasColumn('contractor_documents', 'mime_type')) {
                $table->string('mime_type')->nullable()->after('file_size');
            }

            if (! Schema::hasColumn('contractor_documents', 'storage_driver')) {
                $table->string('storage_driver', 32)->nullable()->after('mime_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contractor_documents')) {
            return;
        }

        Schema::table('contractor_documents', function (Blueprint $table): void {
            foreach (['storage_driver', 'mime_type', 'file_size', 'file_path', 'original_name'] as $column) {
                if (Schema::hasColumn('contractor_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

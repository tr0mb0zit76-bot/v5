<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_form_basic_terms', function (Blueprint $table) {
            $table->id();
            $table->string('party', 16);
            $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('body');
            $table->timestamps();

            $table->index(['party', 'contractor_id', 'sort_order'], 'print_form_basic_terms_scope_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_form_basic_terms');
    }
};

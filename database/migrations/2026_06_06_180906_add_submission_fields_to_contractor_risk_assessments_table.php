<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractor_risk_assessments', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('approved_at');
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->string('submission_reason', 32)->nullable()->after('submitted_by');
        });
    }

    public function down(): void
    {
        Schema::table('contractor_risk_assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['submitted_at', 'submission_reason']);
        });
    }
};

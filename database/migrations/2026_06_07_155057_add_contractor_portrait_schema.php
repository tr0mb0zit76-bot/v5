<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractor_portraits')) {
            Schema::create('contractor_portraits', function (Blueprint $table): void {
                $table->foreignId('contractor_id')->primary()->constrained('contractors')->cascadeOnDelete();
                $table->string('communication_style', 32)->default('unknown');
                $table->string('price_sensitivity', 32)->default('unknown');
                $table->string('preferred_channel', 32)->default('unknown');
                $table->string('decision_cadence', 32)->default('unknown');
                $table->string('relationship_trust', 32)->default('unknown');
                $table->text('success_criteria')->nullable();
                $table->json('typical_objections')->nullable();
                $table->text('internal_notes')->nullable();
                $table->unsignedTinyInteger('coverage_pct')->default(0);
                $table->timestamp('portrait_updated_at')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('contractor_contacts')) {
            Schema::table('contractor_contacts', function (Blueprint $table): void {
                if (! Schema::hasColumn('contractor_contacts', 'role_in_deal')) {
                    $table->string('role_in_deal', 32)->nullable()->after('is_decision_maker');
                }

                if (! Schema::hasColumn('contractor_contacts', 'communication_notes')) {
                    $table->text('communication_notes')->nullable()->after('role_in_deal');
                }
            });
        }

        if (Schema::hasTable('contractor_interactions')) {
            Schema::table('contractor_interactions', function (Blueprint $table): void {
                if (! Schema::hasColumn('contractor_interactions', 'contractor_contact_id')) {
                    $table->foreignId('contractor_contact_id')
                        ->nullable()
                        ->after('contractor_id')
                        ->constrained('contractor_contacts')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('contractor_interactions', 'outcome_code')) {
                    $table->string('outcome_code', 32)->nullable()->after('channel');
                }

                if (! Schema::hasColumn('contractor_interactions', 'next_contact_at')) {
                    $table->timestamp('next_contact_at')->nullable()->after('contacted_at');
                }

                if (! Schema::hasColumn('contractor_interactions', 'objection_tags')) {
                    $table->json('objection_tags')->nullable()->after('result');
                }

                if (! Schema::hasColumn('contractor_interactions', 'merge_to_portrait')) {
                    $table->boolean('merge_to_portrait')->default(false)->after('objection_tags');
                }

                if (
                    Schema::hasTable('mail_messages')
                    && ! Schema::hasColumn('contractor_interactions', 'mail_message_id')
                ) {
                    $table->foreignId('mail_message_id')
                        ->nullable()
                        ->after('merge_to_portrait')
                        ->constrained('mail_messages')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contractor_interactions')) {
            Schema::table('contractor_interactions', function (Blueprint $table): void {
                foreach ([
                    'mail_message_id',
                    'merge_to_portrait',
                    'objection_tags',
                    'next_contact_at',
                    'outcome_code',
                    'contractor_contact_id',
                ] as $column) {
                    if (Schema::hasColumn('contractor_interactions', $column)) {
                        if (in_array($column, ['contractor_contact_id', 'mail_message_id'], true)) {
                            $table->dropForeign([$column]);
                        }

                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('contractor_contacts')) {
            Schema::table('contractor_contacts', function (Blueprint $table): void {
                foreach (['communication_notes', 'role_in_deal'] as $column) {
                    if (Schema::hasColumn('contractor_contacts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('contractor_portraits');
    }
};

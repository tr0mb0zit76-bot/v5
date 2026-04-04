<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('print_form_templates')) {
            Schema::create('print_form_templates', function (Blueprint $table) {
                $table->id();
                $table->string('code', 100)->unique();
                $table->string('name');
                $table->string('document_type', 50)->index();
                $table->string('document_group', 50)->index();
                $table->string('party', 50)->default('internal')->index();
                $table->string('vue_component', 255);
                $table->string('pdf_view', 255)->nullable();
                $table->boolean('requires_internal_signature')->default(true);
                $table->boolean('requires_counterparty_signature')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('version')->default(1);
                $table->json('settings')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });

            if (Schema::hasTable('users')) {
                Schema::table('print_form_templates', function (Blueprint $table) {
                    $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                    $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('order_documents')) {
            Schema::table('order_documents', function (Blueprint $table) {
                if (! Schema::hasColumn('order_documents', 'document_group')) {
                    $table->string('document_group', 50)->nullable()->after('type');
                }

                if (! Schema::hasColumn('order_documents', 'source')) {
                    $table->string('source', 50)->default('uploaded')->after('document_group');
                }

                if (! Schema::hasColumn('order_documents', 'signature_status')) {
                    $table->string('signature_status', 50)->default('not_requested')->after('status');
                }

                if (! Schema::hasColumn('order_documents', 'requires_counterparty_signature')) {
                    $table->boolean('requires_counterparty_signature')->default(false)->after('signature_status');
                }

                if (! Schema::hasColumn('order_documents', 'approval_requested_at')) {
                    $table->timestamp('approval_requested_at')->nullable()->after('requires_counterparty_signature');
                }

                if (! Schema::hasColumn('order_documents', 'approval_requested_by')) {
                    $table->unsignedBigInteger('approval_requested_by')->nullable()->after('approval_requested_at');
                }

                if (! Schema::hasColumn('order_documents', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approval_requested_by');
                }

                if (! Schema::hasColumn('order_documents', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
                }

                if (! Schema::hasColumn('order_documents', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('approved_by');
                }

                if (! Schema::hasColumn('order_documents', 'rejected_by')) {
                    $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_at');
                }

                if (! Schema::hasColumn('order_documents', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('rejected_by');
                }

                if (! Schema::hasColumn('order_documents', 'internal_signed_at')) {
                    $table->timestamp('internal_signed_at')->nullable()->after('rejection_reason');
                }

                if (! Schema::hasColumn('order_documents', 'internal_signed_by')) {
                    $table->unsignedBigInteger('internal_signed_by')->nullable()->after('internal_signed_at');
                }

                if (! Schema::hasColumn('order_documents', 'internal_signed_file_path')) {
                    $table->string('internal_signed_file_path')->nullable()->after('internal_signed_by');
                }

                if (! Schema::hasColumn('order_documents', 'counterparty_signed_at')) {
                    $table->timestamp('counterparty_signed_at')->nullable()->after('internal_signed_file_path');
                }

                if (! Schema::hasColumn('order_documents', 'counterparty_signed_file_path')) {
                    $table->string('counterparty_signed_file_path')->nullable()->after('counterparty_signed_at');
                }

                if (! Schema::hasColumn('order_documents', 'snapshot_payload')) {
                    $table->json('snapshot_payload')->nullable()->after('counterparty_signed_file_path');
                }
            });

            if (Schema::hasTable('print_form_templates') || Schema::hasTable('users')) {
                Schema::table('order_documents', function (Blueprint $table) {
                    if (Schema::hasTable('print_form_templates') && Schema::hasColumn('order_documents', 'template_id')) {
                        try {
                            $table->foreign('template_id')->references('id')->on('print_form_templates')->nullOnDelete();
                        } catch (Throwable) {
                        }
                    }

                    if (Schema::hasTable('users')) {
                        foreach (['approval_requested_by', 'approved_by', 'rejected_by', 'internal_signed_by'] as $column) {
                            if (Schema::hasColumn('order_documents', $column)) {
                                try {
                                    $table->foreign($column)->references('id')->on('users')->nullOnDelete();
                                } catch (Throwable) {
                                }
                            }
                        }
                    }
                });
            }
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_documents')) {
            Schema::table('order_documents', function (Blueprint $table) {
                foreach ([
                    'order_documents_template_id_foreign',
                    'order_documents_approval_requested_by_foreign',
                    'order_documents_approved_by_foreign',
                    'order_documents_rejected_by_foreign',
                    'order_documents_internal_signed_by_foreign',
                ] as $foreignKey) {
                    try {
                        $table->dropForeign($foreignKey);
                    } catch (Throwable) {
                    }
                }

                $columns = array_values(array_filter([
                    Schema::hasColumn('order_documents', 'document_group') ? 'document_group' : null,
                    Schema::hasColumn('order_documents', 'source') ? 'source' : null,
                    Schema::hasColumn('order_documents', 'signature_status') ? 'signature_status' : null,
                    Schema::hasColumn('order_documents', 'requires_counterparty_signature') ? 'requires_counterparty_signature' : null,
                    Schema::hasColumn('order_documents', 'approval_requested_at') ? 'approval_requested_at' : null,
                    Schema::hasColumn('order_documents', 'approval_requested_by') ? 'approval_requested_by' : null,
                    Schema::hasColumn('order_documents', 'approved_at') ? 'approved_at' : null,
                    Schema::hasColumn('order_documents', 'approved_by') ? 'approved_by' : null,
                    Schema::hasColumn('order_documents', 'rejected_at') ? 'rejected_at' : null,
                    Schema::hasColumn('order_documents', 'rejected_by') ? 'rejected_by' : null,
                    Schema::hasColumn('order_documents', 'rejection_reason') ? 'rejection_reason' : null,
                    Schema::hasColumn('order_documents', 'internal_signed_at') ? 'internal_signed_at' : null,
                    Schema::hasColumn('order_documents', 'internal_signed_by') ? 'internal_signed_by' : null,
                    Schema::hasColumn('order_documents', 'internal_signed_file_path') ? 'internal_signed_file_path' : null,
                    Schema::hasColumn('order_documents', 'counterparty_signed_at') ? 'counterparty_signed_at' : null,
                    Schema::hasColumn('order_documents', 'counterparty_signed_file_path') ? 'counterparty_signed_file_path' : null,
                    Schema::hasColumn('order_documents', 'snapshot_payload') ? 'snapshot_payload' : null,
                ]));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('print_form_templates')) {
            Schema::table('print_form_templates', function (Blueprint $table) {
                foreach ([
                    'print_form_templates_created_by_foreign',
                    'print_form_templates_updated_by_foreign',
                ] as $foreignKey) {
                    try {
                        $table->dropForeign($foreignKey);
                    } catch (Throwable) {
                    }
                }
            });

            Schema::dropIfExists('print_form_templates');
        }

        Schema::dropIfExists('notifications');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if (! Schema::hasColumn('leads', 'counterparty_id') || ! Schema::hasColumn('leads', 'responsible_id')) {
                    return;
                }

                try {
                    $table->foreign('counterparty_id')->references('id')->on('contractors')->nullOnDelete();
                } catch (Throwable) {
                }

                try {
                    $table->foreign('responsible_id')->references('id')->on('users')->nullOnDelete();
                } catch (Throwable) {
                }

                try {
                    $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                } catch (Throwable) {
                }

                try {
                    $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('lead_route_points') && Schema::hasTable('leads')) {
            Schema::table('lead_route_points', function (Blueprint $table) {
                try {
                    $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('lead_cargo_items') && Schema::hasTable('leads')) {
            Schema::table('lead_cargo_items', function (Blueprint $table) {
                try {
                    $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('lead_activities') && Schema::hasTable('leads')) {
            Schema::table('lead_activities', function (Blueprint $table) {
                try {
                    $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
                } catch (Throwable) {
                }

                try {
                    $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('lead_offers') && Schema::hasTable('leads')) {
            Schema::table('lead_offers', function (Blueprint $table) {
                try {
                    $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
                } catch (Throwable) {
                }

                try {
                    $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                } catch (Throwable) {
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lead_offers')) {
            Schema::table('lead_offers', function (Blueprint $table) {
                try {
                    $table->dropForeign(['lead_id']);
                } catch (Throwable) {
                }

                try {
                    $table->dropForeign(['created_by']);
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('lead_activities')) {
            Schema::table('lead_activities', function (Blueprint $table) {
                try {
                    $table->dropForeign(['lead_id']);
                } catch (Throwable) {
                }

                try {
                    $table->dropForeign(['created_by']);
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('lead_cargo_items')) {
            Schema::table('lead_cargo_items', function (Blueprint $table) {
                try {
                    $table->dropForeign(['lead_id']);
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('lead_route_points')) {
            Schema::table('lead_route_points', function (Blueprint $table) {
                try {
                    $table->dropForeign(['lead_id']);
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                foreach (['counterparty_id', 'responsible_id', 'created_by', 'updated_by'] as $column) {
                    try {
                        $table->dropForeign([$column]);
                    } catch (Throwable) {
                    }
                }
            });
        }
    }
};

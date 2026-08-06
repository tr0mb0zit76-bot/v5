<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'track_received_date_customer_request')) {
                $table->date('track_received_date_customer_request')->nullable()->after('track_received_date_customer');
            }

            if (! Schema::hasColumn('orders', 'track_received_date_customer_closing')) {
                $table->date('track_received_date_customer_closing')->nullable()->after('track_received_date_customer_request');
            }

            if (! Schema::hasColumn('orders', 'track_received_date_carrier_request')) {
                $table->date('track_received_date_carrier_request')->nullable()->after('track_received_date_carrier');
            }

            if (! Schema::hasColumn('orders', 'track_received_date_carrier_closing')) {
                $table->date('track_received_date_carrier_closing')->nullable()->after('track_received_date_carrier_request');
            }
        });

        if (Schema::hasColumn('orders', 'track_received_date_customer')) {
            DB::table('orders')
                ->whereNotNull('track_received_date_customer')
                ->orderBy('id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('orders')->where('id', $row->id)->update([
                            'track_received_date_customer_request' => $row->track_received_date_customer_request ?? $row->track_received_date_customer,
                            'track_received_date_customer_closing' => $row->track_received_date_customer_closing ?? $row->track_received_date_customer,
                        ]);
                    }
                });
        }

        if (Schema::hasColumn('orders', 'track_received_date_carrier')) {
            DB::table('orders')
                ->whereNotNull('track_received_date_carrier')
                ->orderBy('id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('orders')->where('id', $row->id)->update([
                            'track_received_date_carrier_request' => $row->track_received_date_carrier_request ?? $row->track_received_date_carrier,
                            'track_received_date_carrier_closing' => $row->track_received_date_carrier_closing ?? $row->track_received_date_carrier,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            foreach ([
                'track_received_date_customer_request',
                'track_received_date_customer_closing',
                'track_received_date_carrier_request',
                'track_received_date_carrier_closing',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

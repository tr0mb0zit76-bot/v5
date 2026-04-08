<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (
            ! Schema::hasTable('orders')
            || ! Schema::hasTable('order_legs')
            || ! Schema::hasTable('financial_terms')
            || ! Schema::hasTable('leg_contractor_assignments')
            || ! Schema::hasTable('users')
        ) {
            return;
        }

        $fallbackUserId = DB::table('users')->min('id');
        if ($fallbackUserId === null) {
            return;
        }

        $rows = DB::table('orders')
            ->leftJoin('financial_terms', 'financial_terms.order_id', '=', 'orders.id')
            ->select([
                'orders.id as order_id',
                'orders.carrier_id',
                'orders.manager_id',
                'orders.created_by',
                'orders.updated_by',
                'financial_terms.contractors_costs',
            ])
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('order_legs')
                    ->whereColumn('order_legs.order_id', 'orders.id');
            })
            ->get();

        foreach ($rows as $row) {
            $legs = DB::table('order_legs')
                ->where('order_id', $row->order_id)
                ->orderBy('sequence')
                ->get(['id', 'description', 'sequence']);

            if ($legs->isEmpty()) {
                continue;
            }

            $costs = json_decode((string) ($row->contractors_costs ?? '[]'), true);
            $costs = is_array($costs) ? $costs : [];

            $contractorByStage = collect($costs)
                ->filter(fn (array $item): bool => array_key_exists('stage', $item))
                ->mapWithKeys(function (array $item): array {
                    $stage = trim((string) ($item['stage'] ?? ''));
                    $contractorId = $item['contractor_id'] ?? null;

                    return [$stage => $contractorId !== null ? (int) $contractorId : null];
                });

            foreach ($legs as $index => $leg) {
                $existing = DB::table('leg_contractor_assignments')
                    ->where('order_leg_id', $leg->id)
                    ->exists();

                if ($existing) {
                    continue;
                }

                $stageCandidates = [
                    trim((string) $leg->description),
                    'leg_'.($index + 1),
                    'Плечо '.($index + 1),
                ];

                $contractorId = null;
                foreach ($stageCandidates as $candidate) {
                    if ($candidate !== '' && $contractorByStage->has($candidate)) {
                        $contractorId = $contractorByStage->get($candidate);
                        break;
                    }
                }

                if ($contractorId === null && $index === 0 && $row->carrier_id !== null) {
                    $contractorId = (int) $row->carrier_id;
                }

                if ($contractorId === null) {
                    continue;
                }

                DB::table('leg_contractor_assignments')->insert([
                    'order_leg_id' => $leg->id,
                    'contractor_id' => $contractorId,
                    'assigned_at' => now(),
                    'assigned_by' => (int) ($row->manager_id ?? $row->updated_by ?? $row->created_by ?? $fallbackUserId),
                    'status' => 'confirmed',
                    'notes' => 'Backfilled from financial_terms/orders carrier',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: data backfill is intentionally not reverted.
    }
};

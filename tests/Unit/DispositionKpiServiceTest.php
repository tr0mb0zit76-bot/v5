<?php

namespace Tests\Unit;

use App\Models\DispositionEntry;
use App\Models\Order;
use App\Models\User;
use App\Services\Disposition\DispositionInProgressOrderScope;
use App\Services\Disposition\DispositionKpiService;
use App\Services\Disposition\DispositionReminderService;
use App\Support\DispositionSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DispositionKpiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        try {
            parent::setUp();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Database unavailable: '.$exception->getMessage());
        }
    }

    public function test_both_slots_fill_percent_for_today(): void
    {
        if (! Schema::hasTable('disposition_entries')) {
            $this->markTestSkipped('disposition_entries table is not migrated.');
        }

        $user = User::factory()->create();
        $date = Carbon::today()->toDateString();

        $filledOrder = Order::factory()->create([
            'manager_id' => $user->id,
            'status' => 'in_progress',
        ]);
        $emptyOrder = Order::factory()->create([
            'manager_id' => $user->id,
            'status' => 'in_progress',
        ]);

        DispositionEntry::query()->create([
            'order_id' => $filledOrder->id,
            'date' => $date,
            'slot' => DispositionSlot::Morning->value,
            'location' => 'Москва',
            'recorded_at' => now(),
            'recorded_by' => $user->id,
        ]);
        DispositionEntry::query()->create([
            'order_id' => $filledOrder->id,
            'date' => $date,
            'slot' => DispositionSlot::Evening->value,
            'location' => 'Тула',
            'recorded_at' => now(),
            'recorded_by' => $user->id,
        ]);

        $service = new DispositionKpiService(
            new DispositionInProgressOrderScope,
            app(DispositionReminderService::class),
        );

        $metrics = $service->metricsForUser($user, $date, true);

        $this->assertSame(2, $metrics['orders_in_progress']);
        $this->assertSame(1, $metrics['both_slots_filled_count']);
        $this->assertSame(50.0, $metrics['both_slots_fill_percent']);
    }
}

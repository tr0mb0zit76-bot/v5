<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\Order;
use App\Services\ContractorOperationalStatusService;
use App\Support\ContractorWorkStatus;
use Tests\TestCase;

class ContractorOperationalStatusServiceTest extends TestCase
{
    public function test_enrich_many_for_display_computes_pause_without_persisting(): void
    {
        $contractor = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО Старый перевозчик',
            'work_status' => ContractorWorkStatus::ACTIVE,
            'work_pause_is_automatic' => false,
            'is_active' => true,
        ]);

        $service = app(ContractorOperationalStatusService::class);
        $collection = Contractor::query()->whereKey($contractor->id)->get();

        $service->enrichManyForDisplay($collection);

        $this->assertSame(ContractorWorkStatus::WORK_PAUSE, $collection->first()->work_status);
        $this->assertTrue($collection->first()->work_pause_is_automatic);

        $contractor->refresh();

        $this->assertSame(ContractorWorkStatus::ACTIVE, $contractor->work_status);
        $this->assertFalse($contractor->work_pause_is_automatic);
    }

    public function test_sync_many_persists_operational_pause(): void
    {
        $contractor = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО Старый перевозчик 2',
            'work_status' => ContractorWorkStatus::ACTIVE,
            'work_pause_is_automatic' => false,
            'is_active' => true,
        ]);

        $service = app(ContractorOperationalStatusService::class);
        $collection = Contractor::query()->whereKey($contractor->id)->get();

        $service->syncMany($collection);

        $contractor->refresh();

        $this->assertSame(ContractorWorkStatus::WORK_PAUSE, $contractor->work_status);
        $this->assertTrue($contractor->work_pause_is_automatic);
    }

    public function test_enrich_many_for_display_restores_active_when_recent_order_exists(): void
    {
        $contractor = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО Активный перевозчик',
            'work_status' => ContractorWorkStatus::WORK_PAUSE,
            'work_pause_is_automatic' => true,
            'is_active' => true,
        ]);

        Order::query()->create([
            'order_date' => now()->subMonth()->toDateString(),
            'carrier_id' => $contractor->id,
        ]);

        $service = app(ContractorOperationalStatusService::class);
        $collection = Contractor::query()->whereKey($contractor->id)->get();

        $service->enrichManyForDisplay($collection);

        $this->assertSame(ContractorWorkStatus::ACTIVE, $collection->first()->work_status);
        $this->assertFalse($collection->first()->work_pause_is_automatic);

        $contractor->refresh();

        $this->assertSame(ContractorWorkStatus::WORK_PAUSE, $contractor->work_status);
        $this->assertTrue($contractor->work_pause_is_automatic);
    }
}

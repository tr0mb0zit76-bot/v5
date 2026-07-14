<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\Order;
use App\Services\ContractorOperationalStatusService;
use App\Support\ContractorWorkStatus;
use Tests\TestCase;

class ContractorOperationalStatusServiceTest extends TestCase
{
    public function test_enrich_many_for_display_marks_contractor_without_orders_as_in_development_without_persisting(): void
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

        $this->assertSame(ContractorWorkStatus::IN_DEVELOPMENT, $collection->first()->work_status);
        $this->assertFalse($collection->first()->work_pause_is_automatic);

        $contractor->refresh();

        $this->assertSame(ContractorWorkStatus::ACTIVE, $contractor->work_status);
        $this->assertFalse($contractor->work_pause_is_automatic);
    }

    public function test_sync_many_persists_in_development_for_contractor_without_orders(): void
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

        $this->assertSame(ContractorWorkStatus::IN_DEVELOPMENT, $contractor->work_status);
        $this->assertFalse($contractor->work_pause_is_automatic);
    }

    public function test_enrich_many_for_display_activates_developing_contractor_when_recent_order_exists(): void
    {
        $contractor = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО Активный перевозчик',
            'work_status' => ContractorWorkStatus::IN_DEVELOPMENT,
            'work_pause_is_automatic' => false,
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

        $this->assertSame(ContractorWorkStatus::IN_DEVELOPMENT, $contractor->work_status);
        $this->assertFalse($contractor->work_pause_is_automatic);
    }

    public function test_enrich_many_for_display_pauses_contractor_whose_last_order_is_old(): void
    {
        $contractor = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО Неактивный перевозчик',
            'work_status' => ContractorWorkStatus::ACTIVE,
            'work_pause_is_automatic' => false,
            'is_active' => true,
        ]);

        Order::query()->create([
            'order_date' => now()->subMonths(4)->toDateString(),
            'carrier_id' => $contractor->id,
        ]);

        $service = app(ContractorOperationalStatusService::class);
        $collection = Contractor::query()->whereKey($contractor->id)->get();

        $service->enrichManyForDisplay($collection);

        $this->assertSame(ContractorWorkStatus::WORK_PAUSE, $collection->first()->work_status);
        $this->assertTrue($collection->first()->work_pause_is_automatic);
    }

    public function test_work_ban_is_preserved_for_contractor_without_orders(): void
    {
        $contractor = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО Заблокированный перевозчик',
            'work_status' => ContractorWorkStatus::WORK_BAN,
            'work_pause_is_automatic' => false,
            'is_active' => true,
        ]);

        $service = app(ContractorOperationalStatusService::class);
        $collection = Contractor::query()->whereKey($contractor->id)->get();

        $service->enrichManyForDisplay($collection);

        $this->assertSame(ContractorWorkStatus::WORK_BAN, $collection->first()->work_status);
    }
}

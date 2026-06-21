<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderPortalInviteService;
use Tests\TestCase;

class OrderPortalInviteServiceTest extends TestCase
{
    private OrderPortalInviteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderPortalInviteService::class);
    }

    public function test_token_hash_is_deterministic(): void
    {
        $hashOne = $this->service->hashToken('abc-token');
        $hashTwo = $this->service->hashToken('abc-token');

        $this->assertSame($hashOne, $hashTwo);
        $this->assertSame(64, strlen($hashOne));
    }

    public function test_normalize_stage_identifier(): void
    {
        $this->assertSame('leg_1', $this->service->normalizeStageIdentifier(''));
        $this->assertSame('leg_2', $this->service->normalizeStageIdentifier('leg_2'));
        $this->assertSame('leg_3', $this->service->normalizeStageIdentifier('3'));
        $this->assertSame('leg_4', $this->service->normalizeStageIdentifier('Плечо 4'));
    }

    public function test_is_contractor_assigned_on_order_when_stage_label_is_used(): void
    {
        $order = new Order([
            'carrier_id' => null,
            'performers' => [
                [
                    'stage' => 'Плечо 2',
                    'carrier_mode' => 'single',
                    'contractor_id' => 42,
                ],
            ],
        ]);

        $this->assertTrue($this->service->isContractorAssignedOnOrder($order, 42, 'Плечо 2', 1));
        $this->assertTrue($this->service->isContractorAssignedOnOrder($order, 42, 'leg_2', 1));
        $this->assertFalse($this->service->isContractorAssignedOnOrder($order, 99, 'leg_2', 1));
    }

    public function test_expand_split_carrier_rows_requires_matching_slot(): void
    {
        $order = new Order([
            'performers' => [
                [
                    'stage' => 'leg_1',
                    'carrier_mode' => 'split',
                    'split_carriers' => [
                        ['slot' => 1, 'contractor_id' => 10],
                        ['slot' => 2, 'contractor_id' => 20],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($this->service->isContractorAssignedOnOrder($order, 20, 'leg_1', 2));
        $this->assertFalse($this->service->isContractorAssignedOnOrder($order, 20, 'leg_1', 1));
    }
}

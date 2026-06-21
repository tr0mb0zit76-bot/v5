<?php

namespace Tests\Unit;

use App\Services\OrderDocumentRequirementService;
use Tests\TestCase;

class OrderCarrierPortalDocumentRulesTest extends TestCase
{
    public function test_rules_for_carrier_portal_invite_filter_by_contractor_stage_and_slot(): void
    {
        $service = app(OrderDocumentRequirementService::class);

        $performers = [
            [
                'stage' => 'leg_1',
                'carrier_mode' => 'split',
                'split_carriers' => [
                    [
                        'slot' => 1,
                        'contractor_id' => 10,
                        'contractor_name' => 'Перевозчик А',
                    ],
                    [
                        'slot' => 2,
                        'contractor_id' => 20,
                        'contractor_name' => 'Перевозчик Б',
                    ],
                ],
            ],
        ];

        $rulesForTen = $service->rulesForCarrierPortalInviteFromPerformers($performers, 'single_request', 10, 'leg_1', 1);
        $rulesForTwenty = $service->rulesForCarrierPortalInviteFromPerformers($performers, 'single_request', 20, 'leg_1', 2);

        $this->assertNotEmpty($rulesForTen);
        $this->assertNotEmpty($rulesForTwenty);

        foreach ($rulesForTen as $rule) {
            $this->assertSame('carrier', $rule['party']);
            $this->assertSame(10, $rule['contractor_id']);
            $this->assertStringEndsWith('-slot1', (string) $rule['slot_key']);
        }

        foreach ($rulesForTwenty as $rule) {
            $this->assertSame(20, $rule['contractor_id']);
            $this->assertStringEndsWith('-slot2', (string) $rule['slot_key']);
        }
    }
}

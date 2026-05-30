<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderPortalInvite;
use App\Services\OrderPortalInviteAccessService;
use App\Services\OrderPortalInviteService;
use Tests\TestCase;

class OrderPortalInviteAccessServiceTest extends TestCase
{
    public function test_link_closes_when_unloading_actual_is_set_on_matching_performer(): void
    {
        $order = new Order([
            'performers' => [[
                'stage' => 'leg_1',
                'contractor_id' => 42,
                'carrier_mode' => 'single',
                'unloading_actual' => '2026-05-28',
            ]],
        ]);

        $invite = new OrderPortalInvite([
            'order_id' => 1,
            'contractor_id' => 42,
            'stage' => 'leg_1',
            'carrier_slot' => 1,
        ]);
        $invite->setRelation('order', $order);

        $service = new OrderPortalInviteAccessService(new OrderPortalInviteService);

        $this->assertTrue($service->isClosedBecauseUnloadingComplete($order, $invite));
        $this->assertFalse($service->canUploadDocuments($order, $invite));
    }

    public function test_link_stays_open_without_unloading_actual(): void
    {
        $order = new Order([
            'performers' => [[
                'stage' => 'leg_1',
                'contractor_id' => 42,
                'carrier_mode' => 'single',
            ]],
        ]);

        $invite = new OrderPortalInvite([
            'order_id' => 1,
            'contractor_id' => 42,
            'stage' => 'leg_1',
            'carrier_slot' => 1,
        ]);
        $invite->setRelation('order', $order);

        $service = new OrderPortalInviteAccessService(new OrderPortalInviteService);

        $this->assertFalse($service->isClosedBecauseUnloadingComplete($order, $invite));
        $this->assertTrue($service->canUploadDocuments($order, $invite));
    }

    public function test_submitted_invite_can_still_upload_documents_before_unloading(): void
    {
        $order = new Order([
            'performers' => [[
                'stage' => 'leg_1',
                'contractor_id' => 42,
                'carrier_mode' => 'single',
            ]],
        ]);

        $invite = new OrderPortalInvite([
            'order_id' => 1,
            'contractor_id' => 42,
            'stage' => 'leg_1',
            'carrier_slot' => 1,
            'used_at' => now(),
        ]);
        $invite->setRelation('order', $order);

        $service = new OrderPortalInviteAccessService(new OrderPortalInviteService);

        $this->assertTrue($service->canUploadDocuments($order, $invite));
        $this->assertFalse($service->canSubmitFleetForm($order, $invite));
    }
}

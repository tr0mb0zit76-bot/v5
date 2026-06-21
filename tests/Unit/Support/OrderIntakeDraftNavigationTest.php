<?php

namespace Tests\Unit\Support;

use App\Support\OrderIntakeDraftNavigation;
use Tests\TestCase;

class OrderIntakeDraftNavigationTest extends TestCase
{
    public function test_path_after_create_draft_tool_returns_wizard_url(): void
    {
        $path = OrderIntakeDraftNavigation::pathAfterCreateDraftTool('create_order_intake_draft_from_text', [
            'draft_id' => 42,
            'wizard_path' => '/orders/create?intake_draft=42',
        ]);

        $this->assertSame('/orders/create?intake_draft=42', $path);
    }

    public function test_path_after_create_draft_tool_skips_error_results(): void
    {
        $path = OrderIntakeDraftNavigation::pathAfterCreateDraftTool('create_order_intake_draft_from_text', [
            'error' => 'Не удалось структурировать заявку.',
            'draft_id' => 42,
        ]);

        $this->assertNull($path);
    }
}

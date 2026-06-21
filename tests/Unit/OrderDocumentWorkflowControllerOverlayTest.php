<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Полный HTTP-тест с БД в этом проекте упирается в несовместимость части миграций с sqlite :memory: из phpunit.xml.
 * Здесь зафиксировано намерение: предпросмотр в заказе без позиционирования печати/подписи; POST из заказа запрещён.
 */
class OrderDocumentWorkflowControllerOverlayTest extends TestCase
{
    public function test_preview_draft_disables_overlay_adjustment_in_controller(): void
    {
        $path = dirname(__DIR__, 2).'/app/Http/Controllers/Orders/OrderDocumentWorkflowController.php';
        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString("'canAdjustOverlay' => false", $contents);
        $this->assertStringContainsString("'signatureOverlayImageUrl' => \$signatureOverlayImageUrl", $contents);
        $this->assertStringContainsString("'stampOverlayImageUrl' => \$stampOverlayImageUrl", $contents);
    }

    public function test_update_overlay_positions_aborts_forbidden(): void
    {
        $path = dirname(__DIR__, 2).'/app/Http/Controllers/Orders/OrderDocumentWorkflowController.php';
        $contents = (string) file_get_contents($path);
        $this->assertMatchesRegularExpression(
            '/function\s+updateOverlayPositions[\s\S]*?abort\s*\(\s*403/s',
            $contents
        );
    }
}

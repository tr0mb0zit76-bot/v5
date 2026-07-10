<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LoadingPlannerLayoutTest extends TestCase
{
    public function test_loading_planner_layout_module_is_bundled_for_frontend(): void
    {
        $path = dirname(__DIR__, 2).'/resources/js/support/loadingPlannerLayout.js';

        $this->assertFileExists($path);
        $contents = file_get_contents($path);

        $this->assertStringContainsString('findNextAutoPosition', $contents);
        $this->assertStringContainsString('blocksOverlap', $contents);
        $this->assertStringContainsString('settleTrailerBlocks', $contents);
        $this->assertStringContainsString('findSupportedZForBlock', $contents);
        $this->assertStringContainsString('findBestAutoPlacement', $contents);
        $this->assertStringContainsString('findTopSupportedZForBlock', $contents);
        $this->assertStringContainsString('computeSeriesAlignHints', $contents);
        $this->assertStringContainsString('stackTierForBlock', $contents);
        $this->assertStringContainsString('blocksShareStackColumn', $contents);
        $this->assertStringContainsString('blockCanBeLifted', $contents);
        $this->assertStringContainsString('freezeBase', $contents);
        $this->assertStringContainsString('snapshotPlacementsFromBlocks', $contents);
        $this->assertStringContainsString('sceneBlockPaintOrder', $contents);
        $this->assertStringContainsString('sortBlocksForScenePaint', $contents);
        $this->assertStringContainsString('verticalStackGapMm', $contents);
        $this->assertStringContainsString('buildLengthRulerTicks', $contents);
        $this->assertStringContainsString('buildHeightRulerTicks', $contents);
        $this->assertStringContainsString('calculateMultiVehicleLayout', $contents);
        $this->assertStringContainsString('unitFitsTransportDimensions', $contents);
    }

    public function test_multi_vehicle_layout_splits_overflow_cargo(): void
    {
        $node = trim((string) shell_exec('where node 2>nul') ?: '');
        if ($node === '') {
            $this->markTestSkipped('Node.js is not available in PATH');
        }

        $entry = __DIR__.'/multi_vehicle_layout.test.mjs';
        $this->assertFileExists($entry);

        $output = [];
        $exitCode = 0;
        exec('node '.escapeshellarg($entry).' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_scene_paint_order_sorts_lower_stack_tier_before_upper(): void
    {
        $node = trim((string) shell_exec('where node 2>nul') ?: '');
        if ($node === '') {
            $this->markTestSkipped('Node.js is not available in PATH');
        }

        $entry = __DIR__.'/scene_paint_order.test.mjs';
        $this->assertFileExists($entry);

        $output = [];
        $exitCode = 0;
        exec('node '.escapeshellarg($entry).' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}

<?php

namespace Tests\Unit\Services\ManagementAccounting;

use App\Services\ManagementAccounting\ManagementAccountingFullPictureService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ManagementAccountingFullPictureServiceTest extends TestCase
{
    public function test_build_returns_empty_contours_without_tables(): void
    {
        $result = app(ManagementAccountingFullPictureService::class)->build(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
        );

        $this->assertSame(0.0, $result['operational']['net']);
        $this->assertSame(0.0, $result['management']['net']);
        $this->assertSame(0.0, $result['combined']['net']);
        $this->assertSame([], $result['rows']);
    }
}

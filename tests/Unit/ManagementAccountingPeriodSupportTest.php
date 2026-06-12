<?php

namespace Tests\Unit;

use App\Support\ManagementAccountingPeriodSupport;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManagementAccountingPeriodSupportTest extends TestCase
{
    #[Test]
    public function it_matches_column_by_normalized_datetime_string(): void
    {
        $columns = [
            ['key' => '2026-06-03', 'start' => '2026-06-03', 'end' => '2026-06-03'],
            ['key' => '2026-06-04', 'start' => '2026-06-04', 'end' => '2026-06-04'],
        ];

        $key = ManagementAccountingPeriodSupport::columnKeyForDate($columns, '2026-06-03 00:00:00');

        $this->assertSame('2026-06-03', $key);
    }

    #[Test]
    public function it_returns_short_russian_month_labels(): void
    {
        $label = ManagementAccountingPeriodSupport::pivotMonthLabel(CarbonImmutable::parse('2026-01-15'));

        $this->assertSame('янв', $label);
    }
}

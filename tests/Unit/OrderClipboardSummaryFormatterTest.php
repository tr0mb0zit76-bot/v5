<?php

namespace Tests\Unit;

use App\Support\OrderClipboardSummaryFormatter;
use PHPUnit\Framework\TestCase;

class OrderClipboardSummaryFormatterTest extends TestCase
{
    public function test_formats_route_vehicle_and_driver_parts(): void
    {
        $summary = OrderClipboardSummaryFormatter::format(
            'Москва',
            'Казань',
            'MAN',
            'А123АА77',
            'Schmitz',
            'В456ВВ77',
            'Иванов Иван Иванович',
            '4010 123456',
        );

        $this->assertStringContainsString('Маршрут: Москва — Казань', $summary);
        $this->assertStringContainsString('тягач MAN А123АА77', $summary);
        $this->assertStringContainsString('прицеп Schmitz В456ВВ77', $summary);
        $this->assertStringContainsString('Иванов Иван Иванович, паспорт 4010 123456', $summary);
    }

    public function test_uses_dash_placeholders_for_missing_values(): void
    {
        $summary = OrderClipboardSummaryFormatter::format(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );

        $this->assertSame('Маршрут: — — —; ТС: —; Водитель: —', $summary);
    }
}

<?php

namespace Tests\Unit;

use App\Support\OrderClipboardSummaryFormatter;
use Tests\TestCase;

class OrderClipboardSummaryFormatterTest extends TestCase
{
    public function test_formats_clerk_transport_summary(): void
    {
        $summary = OrderClipboardSummaryFormatter::format(
            'AA',
            'ООО Клиент',
            'ORD-1001',
            '2026-05-20',
            120000,
            'no_vat',
            'Москва',
            'Казань',
            'MAN',
            'А123АА77',
            'Schmitz',
            'В456ВВ77',
            'Иванов Иван Иванович',
        );

        $this->assertStringContainsString('AA ООО Клиент заявка № ORD-1001 от 20.05.2026, 120 000,00 руб., Без НДС', $summary);
        $this->assertStringContainsString('Транспортно-экспедиционные услуги по Заявке № ORD-1001 от 20.05.2026', $summary);
        $this->assertStringContainsString('к Договору транспортной экспедиции (публичной оферте) от 29.05.2026 г.', $summary);
        $this->assertStringContainsString('маршрут Москва - Казань', $summary);
        $this->assertStringContainsString('Водитель Иванов Иван Иванович', $summary);
        $this->assertStringContainsString('ТС MAN / А123АА77 / Schmitz / В456ВВ77', $summary);
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
            null,
            null,
            null,
            null,
            null,
        );

        $this->assertStringContainsString('— — заявка № — от —, —, —', $summary);
        $this->assertStringContainsString('маршрут — - —', $summary);
        $this->assertStringContainsString('Водитель —, ТС —.', $summary);
    }

    public function test_vehicle_slash_label_joins_available_parts(): void
    {
        $this->assertSame(
            'MAN / А123АА77 / Schmitz / В456ВВ77',
            OrderClipboardSummaryFormatter::vehicleSlashLabel('MAN', 'А123АА77', 'Schmitz', 'В456ВВ77'),
        );
    }
}

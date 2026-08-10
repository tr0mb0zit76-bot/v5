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

    public function test_formats_one_c_service_content_body(): void
    {
        $content = OrderClipboardSummaryFormatter::formatServiceContent(
            'АС-ТД-486',
            '2026-07-07',
            'Васюринская',
            'Братск',
            'ГАЗ (Газель) 3302',
            'Х 393 МУ 193',
            null,
            null,
            'Пронин Валерий Александрович',
        );

        $this->assertStringContainsString('Транспортно-экспедиционные услуги по Заявке № АС-ТД-486 от 07.07.2026', $content);
        $this->assertStringContainsString('маршрут: Васюринская - Братск', $content);
        $this->assertStringContainsString('Водитель Пронин Валерий Александрович', $content);
        $this->assertStringContainsString('ТС: ГАЗ (Газель) 3302 / Х 393 МУ 193', $content);
        $this->assertStringNotContainsString('заявка №', $content);
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

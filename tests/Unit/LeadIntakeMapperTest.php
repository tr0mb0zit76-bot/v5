<?php

namespace Tests\Unit;

use App\Support\LeadIntakeMapper;
use App\Support\TransportIntakeHeuristicParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeadIntakeMapperTest extends TestCase
{
    #[Test]
    public function it_maps_extracted_order_intake_shape_to_lead_fields(): void
    {
        $extracted = [
            'customer' => [
                'name' => 'ООО Ромашка',
                'contact_name' => 'Иван',
                'contact_phone' => '+7 999 000 11 22',
            ],
            'route' => [
                'loading' => ['address' => 'Смоленск', 'planned_date' => '2026-07-10'],
                'unloading' => ['address' => 'Москва'],
            ],
            'cargo' => ['name' => 'паллеты 3 т'],
            'confidence' => 0.91,
        ];

        $mapped = LeadIntakeMapper::fromExtracted($extracted, 'исходный текст', 'llm');

        $this->assertSame('llm', $mapped['parsed']['parser']);
        $this->assertSame(0.91, $mapped['parsed']['confidence']);
        $this->assertSame('Смоленск', $mapped['parsed']['loading_location']);
        $this->assertSame('Москва', $mapped['parsed']['unloading_location']);
        $this->assertSame('паллеты 3 т', $mapped['parsed']['cargo']);
        $this->assertSame('+7 999 000 11 22', $mapped['parsed']['phone']);
        $this->assertSame('2026-07-10', $mapped['lead_attributes']['planned_shipping_date']);
        $this->assertSame('паллеты 3 т', $mapped['metadata_intake']['cargo']);
    }

    #[Test]
    public function it_converts_heuristic_parser_output_to_extracted_shape(): void
    {
        $heuristic = TransportIntakeHeuristicParser::parse(
            'Прошу рассчитать стоимость перевозки из Смоленска в Москву, груз паллеты 3 тонны, телефон +7 999 000 11 22',
        );

        $extracted = LeadIntakeMapper::heuristicToExtractedShape($heuristic);

        $this->assertSame('Смоленска', $extracted['route']['loading']['address']);
        $this->assertSame('Москву', $extracted['route']['unloading']['address']);
        $this->assertSame('паллеты 3 тонны', $extracted['cargo']['name']);
        $this->assertSame('+7 999 000 11 22', $extracted['customer']['contact_phone']);
    }
}

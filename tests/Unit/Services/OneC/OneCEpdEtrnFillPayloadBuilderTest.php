<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Services\OneC\OneCEpdEtrnFillPayloadBuilder;
use PHPUnit\Framework\TestCase;

class OneCEpdEtrnFillPayloadBuilderTest extends TestCase
{
    public function test_builds_fill_json_with_index_building_aliases(): void
    {
        $builder = new OneCEpdEtrnFillPayloadBuilder;
        $payload = $builder->build('ref-1', [
            'organization_ref' => 'org-1',
            'order_id' => 214,
            'order_number' => 'АС-ТД-968',
            'document_date' => '2026-09-07',
            'counterparty' => [
                'inn' => '5835139805',
                'kpp' => '583501001',
                'name' => 'НОВАФАРМ',
                'phone' => '+7900',
            ],
            'parties' => [
                'carrier' => [
                    'inn' => '5245031478',
                    'name' => 'МИРАРИТА',
                    'phone' => '+7911',
                ],
            ],
            'route' => [
                'loading' => [
                    'address' => 'город А',
                    'postal_code' => '440000',
                    'city' => 'А',
                    'region' => 'Обл',
                    'street' => 'ул',
                    'house' => '1',
                    'planned_date' => '2026-09-07',
                ],
                'unloading' => [
                    'address' => 'город Б',
                    'city' => 'Б',
                    'planned_date' => '2026-09-08',
                ],
            ],
            'cargo' => [
                ['title' => 'таблетки', 'weight' => 100.5, 'volume' => 2, 'package_count' => 10],
            ],
            'driver' => ['name' => 'Иванов И.И.'],
            'vehicle' => [
                'tractor_brand' => 'Volvo',
                'tractor_plate' => 'A111AA777',
                'trailer_brand' => null,
                'trailer_plate' => 'B222BB77',
            ],
        ], 'cust-ref', 'car-ref');

        $this->assertSame('ref-1', $payload['etrn_ref']);
        $this->assertSame('org-1', $payload['organization_ref']);
        $this->assertSame(214, $payload['crm_order']['id']);
        $this->assertSame('cust-ref', $payload['parties']['customer']['ref']);
        $this->assertSame('car-ref', $payload['parties']['carrier']['ref']);
        $this->assertSame('440000', $payload['route']['loading']['index']);
        $this->assertSame('1', $payload['route']['loading']['building']);
        $this->assertSame(100.5, $payload['cargo'][0]['weight_kg']);
        $this->assertSame('Иванов И.И.', $payload['driver']['full_name']);
        $this->assertSame('A111AA777', $payload['vehicle']['tractor_plate']);
    }
}

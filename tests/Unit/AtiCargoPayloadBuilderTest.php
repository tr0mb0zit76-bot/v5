<?php

namespace Tests\Unit;

use App\Models\Cargo;
use App\Services\AtiCargoPayloadBuilder;
use PHPUnit\Framework\TestCase;

class AtiCargoPayloadBuilderTest extends TestCase
{
    public function test_it_builds_payload_from_ati_shaped_cargo_fields(): void
    {
        $cargo = new Cargo([
            'title' => 'Legacy title',
            'ati_cargo_name' => 'Станок',
            'description' => 'Тяжелый станок',
            'weight_value' => 1.25,
            'weight_unit' => 't',
            'volume' => 7.5,
            'length' => 2.5,
            'width' => 2,
            'height' => 1.5,
            'cargo_type_id' => 4,
            'cargo_type' => 'oversized',
            'cargo_type_label' => 'Негабаритный груз',
            'pack_type_id' => 3,
            'packing_type' => 'crate',
            'pack_type_label' => 'Ящик',
            'loading_type_id' => 5,
            'loading_type_code' => 'tail_lift',
            'loading_type_label' => 'Гидроборт',
            'loading_type_items' => [
                ['id' => 5, 'code' => 'tail_lift', 'label' => 'Гидроборт'],
                ['id' => 6, 'code' => 'crane', 'label' => 'Манипулятор'],
            ],
            'truck_body_type_id' => 1,
            'truck_body_type_code' => 'all_closed',
            'truck_body_type_label' => 'Все закрытые',
            'truck_body_type_items' => [
                ['id' => 1, 'code' => 'all_closed', 'label' => 'Все закрытые'],
                ['id' => 2, 'code' => 'all_open', 'label' => 'Все открытые'],
            ],
            'trailer_type_id' => 1,
            'trailer_type_code' => 'semi_trailer',
            'trailer_type_label' => 'Полуприцеп',
            'trailer_type_items' => [
                ['id' => 1, 'code' => 'semi_trailer', 'label' => 'Полуприцеп'],
            ],
            'package_count' => 2,
            'is_hazardous' => true,
            'hazard_class' => '3',
            'is_oversized' => true,
            'ati_cargo_payload' => [
                'customField' => 'from-extra-payload',
            ],
        ]);

        $payload = (new AtiCargoPayloadBuilder)->build($cargo);

        $this->assertSame('Станок', $payload['name']);
        $this->assertSame(['value' => 1.25, 'unit' => 't'], $payload['weight']);
        $this->assertSame([
            'length' => 2.5,
            'width' => 2.0,
            'height' => 1.5,
            'unit' => 'm',
        ], $payload['sizes']);
        $this->assertSame(4, $payload['cargoTypeId']);
        $this->assertSame(3, $payload['packaging']['packTypeId']);
        $this->assertSame(2, $payload['packaging']['places']);
        $this->assertSame(5, $payload['loading']['loadingTypeId']);
        $this->assertSame('tail_lift', $payload['loading']['loadingType']);
        $this->assertSame('Гидроборт', $payload['loading']['loadingTypeName']);
        $this->assertSame('crane', $payload['loading']['loadingTypes'][1]['code']);
        $this->assertSame(1, $payload['transport']['truckBodyTypeId']);
        $this->assertSame('all_open', $payload['transport']['truckBodyTypes'][1]['code']);
        $this->assertSame('semi_trailer', $payload['transport']['trailerType']);
        $this->assertSame(['isHazardous' => true, 'class' => '3'], $payload['hazard']);
        $this->assertSame(['oversized' => true], $payload['flags']);
        $this->assertSame('from-extra-payload', $payload['customField']);
    }
}

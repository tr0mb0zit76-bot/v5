<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\OneCRoutePointAddressFields;
use Tests\TestCase;

class OneCRoutePointAddressFieldsTest extends TestCase
{
    public function test_splits_normalized_data_into_separate_fields(): void
    {
        $fields = OneCRoutePointAddressFields::fromRoutePoint([
            'address' => 'Волгоградская обл, г Котельниково, ул Ленина, д 1, кв 12',
            'normalized_data' => [
                'region' => 'Волгоградская обл',
                'city' => 'Котельниково',
                'street' => 'ул Ленина',
                'house' => '1',
                'flat' => '12',
                'postal_code' => '404354',
            ],
        ]);

        $this->assertSame('Волгоградская обл, г Котельниково, ул Ленина, д 1, кв 12', $fields['address']);
        $this->assertSame('Волгоградская обл', $fields['region']);
        $this->assertSame('Котельниково', $fields['city']);
        $this->assertSame('ул Ленина', $fields['street']);
        $this->assertSame('1', $fields['house']);
        $this->assertSame('12', $fields['flat']);
        $this->assertSame('404354', $fields['postal_code']);
    }

    public function test_falls_back_to_city_from_address_when_normalized_empty(): void
    {
        $fields = OneCRoutePointAddressFields::fromRoutePoint([
            'address' => 'Волгоградская обл, г Котельниково',
            'normalized_data' => [],
        ]);

        $this->assertSame('Волгоградская обл, г Котельниково', $fields['address']);
        // Эвристика берёт первый сегмент до запятой — без DaData это не «город».
        $this->assertSame('Волгоградская обл', $fields['city']);
        $this->assertNull($fields['region']);
        $this->assertNull($fields['street']);
        $this->assertNull($fields['house']);
        $this->assertNull($fields['flat']);
        $this->assertNull($fields['postal_code']);
    }
}

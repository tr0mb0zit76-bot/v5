<?php

namespace Tests\Unit\Support;

use App\Support\CargoDimensionUnit;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CargoDimensionUnitTest extends TestCase
{
    public function test_normalizes_unknown_unit_to_meters(): void
    {
        $this->assertSame('m', CargoDimensionUnit::normalize(null));
        $this->assertSame('m', CargoDimensionUnit::normalize(''));
        $this->assertSame('m', CargoDimensionUnit::normalize('ft'));
        $this->assertSame('cm', CargoDimensionUnit::normalize('CM'));
        $this->assertSame('mm', CargoDimensionUnit::normalize('mm'));
    }

    #[DataProvider('toMetersProvider')]
    public function test_converts_to_meters(?float $value, string $unit, ?float $expected): void
    {
        $this->assertSame($expected, CargoDimensionUnit::toMeters($value, $unit));
    }

    /**
     * @return array<string, array{0: ?float, 1: string, 2: ?float}>
     */
    public static function toMetersProvider(): array
    {
        return [
            'null' => [null, 'cm', null],
            'meters' => [2.5, 'm', 2.5],
            'centimeters' => [250.0, 'cm', 2.5],
            'millimeters' => [2500.0, 'mm', 2.5],
        ];
    }

    public function test_resolve_from_payload_converts_display_values(): void
    {
        $resolved = CargoDimensionUnit::resolveFromPayload([
            'length_value' => 250,
            'width_value' => 200,
            'height_value' => 150,
            'dimension_unit' => 'cm',
        ]);

        $this->assertSame('cm', $resolved['unit']);
        $this->assertSame(250.0, $resolved['length']);
        $this->assertSame(2.5, $resolved['length_m']);
        $this->assertSame(2.0, $resolved['width_m']);
        $this->assertSame(1.5, $resolved['height_m']);
        $this->assertSame(7.5, CargoDimensionUnit::volumeM3(
            $resolved['length_m'],
            $resolved['width_m'],
            $resolved['height_m'],
        ));
    }

    public function test_resolve_from_payload_keeps_legacy_meters(): void
    {
        $resolved = CargoDimensionUnit::resolveFromPayload([
            'length_m' => 2.5,
            'width_m' => 2,
            'height_m' => 1.5,
        ]);

        $this->assertSame('m', $resolved['unit']);
        $this->assertSame(2.5, $resolved['length']);
        $this->assertSame(2.5, $resolved['length_m']);
    }
}

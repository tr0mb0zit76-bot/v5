<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Services\OrderNumberGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class OrderNumberGeneratorTest extends TestCase
{
    /**
     * @return list<array{0: string, 1: array<string, mixed>, 2: string}>
     */
    public static function companyCodeProvider(): array
    {
        return [
            ['ООО Альфа-Плюс Перевозки', [], 'АПП'],
            ['ООО Логистика России', [], 'ЛР'],
            ['ООО «Логистика России»', [], 'ЛР'],
            ['ooo Альфа Плюс Перевозки', [], 'АПП'],
            ['—', ['order_company_code' => 'lr-01'], 'LR01'],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    #[DataProvider('companyCodeProvider')]
    public function test_resolve_company_code_uses_cyrillic_abbreviation(string $name, array $metadata, string $expected): void
    {
        $contractor = new Contractor([
            'name' => $name,
            'metadata' => $metadata,
        ]);

        $generator = new OrderNumberGenerator;
        $method = new ReflectionMethod(OrderNumberGenerator::class, 'resolveCompanyCode');
        $result = $method->invoke($generator, $contractor);

        $this->assertSame($expected, $result);
    }

    public function test_explicit_metadata_code_takes_precedence_over_name(): void
    {
        $contractor = new Contractor([
            'name' => 'ООО Логистика России',
            'metadata' => ['order_company_code' => '  XX '],
        ]);

        $generator = new OrderNumberGenerator;
        $method = new ReflectionMethod(OrderNumberGenerator::class, 'resolveCompanyCode');

        $this->assertSame('XX', $method->invoke($generator, $contractor));
    }
}

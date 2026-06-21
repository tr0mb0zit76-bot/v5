<?php

namespace Tests\Unit;

use App\Services\Finance\FinanceOverviewService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class FinanceOverviewCarrierLabelTest extends TestCase
{
    #[DataProvider('carrierCountLabelProvider')]
    public function test_carrier_count_label_uses_correct_russian_plural(int $count, string $expected): void
    {
        $service = app(FinanceOverviewService::class);
        $method = new ReflectionMethod(FinanceOverviewService::class, 'carrierCountLabel');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($service, $count));
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function carrierCountLabelProvider(): array
    {
        return [
            'one' => [1, '1 перевозчик'],
            'two' => [2, '2 перевозчика'],
            'three' => [3, '3 перевозчика'],
            'five' => [5, '5 перевозчиков'],
            'eleven' => [11, '11 перевозчиков'],
            'twenty_one' => [21, '21 перевозчик'],
        ];
    }
}

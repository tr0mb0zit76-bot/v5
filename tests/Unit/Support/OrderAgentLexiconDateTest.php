<?php

namespace Tests\Unit\Support;

use App\Support\OrderAgentLexicon;
use App\Support\OrderIntakeSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderAgentLexiconDateTest extends TestCase
{
    #[DataProvider('invalidDateProvider')]
    public function test_normalize_date_value_rejects_invalid_dates(mixed $input): void
    {
        $this->assertNull(OrderAgentLexicon::normalizeDateValue($input));
    }

    /**
     * @return list<array{0: mixed}>
     */
    public static function invalidDateProvider(): array
    {
        return [
            ['завтра'],
            ['2026-13-05'],
            ['2026-02-30'],
            [''],
            [null],
        ];
    }

    #[DataProvider('validDateProvider')]
    public function test_normalize_date_value_accepts_valid_dates(mixed $input, string $expected): void
    {
        $this->assertSame($expected, OrderAgentLexicon::normalizeDateValue($input));
    }

    /**
     * @return list<array{0: mixed, 1: string}>
     */
    public static function validDateProvider(): array
    {
        return [
            ['2026-06-03', '2026-06-03'],
            ['03.06.2026', '2026-06-03'],
            ['3.6.26', '2026-06-03'],
        ];
    }

    public function test_sanitize_wizard_patch_strips_invalid_route_dates(): void
    {
        $sanitized = OrderIntakeSchema::sanitizeWizardPatch([
            'order_date' => 'завтра',
            'loading_date' => '2026-13-01',
            'route_points' => [
                ['type' => 'loading', 'planned_date' => '2026-06-03'],
                ['type' => 'unloading', 'planned_date' => 'invalid'],
            ],
        ]);

        $this->assertNull($sanitized['order_date']);
        $this->assertNull($sanitized['loading_date']);
        $this->assertSame('2026-06-03', $sanitized['route_points'][0]['planned_date']);
        $this->assertNull($sanitized['route_points'][1]['planned_date']);
    }
}

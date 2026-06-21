<?php

namespace Tests\Unit;

use App\Support\OrderAgentLexicon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderAgentLexiconTest extends TestCase
{
    #[DataProvider('routeActualPhraseProvider')]
    public function test_resolves_route_actual_phrases(string $phrase, string $expected): void
    {
        $this->assertSame($expected, OrderAgentLexicon::resolveRouteActualKind($phrase));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function routeActualPhraseProvider(): array
    {
        return [
            'loading key' => ['loading_actual', 'loading_actual'],
            'human loading' => ['фактическая дата загрузки', 'loading_actual'],
            'colloquial' => ['груз забрали', 'loading_actual'],
            'unloading' => ['фактическая выгрузка', 'unloading_actual'],
        ];
    }

    public function test_normalizes_short_russian_date(): void
    {
        $this->assertSame('2026-05-15', OrderAgentLexicon::normalizeDateValue('15.05.2026'));
        $this->assertSame('2026-05-15', OrderAgentLexicon::normalizeDateValue('15.05.26'));
    }

    public function test_label_for_loading_actual(): void
    {
        $this->assertSame('Фактическая дата погрузки', OrderAgentLexicon::labelFor('loading_actual'));
    }
}

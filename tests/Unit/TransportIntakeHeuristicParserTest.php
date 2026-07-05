<?php

namespace Tests\Unit;

use App\Support\TransportIntakeHeuristicParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransportIntakeHeuristicParserTest extends TestCase
{
    #[Test]
    public function it_parses_route_cargo_and_phone_from_message(): void
    {
        $parsed = TransportIntakeHeuristicParser::parse(
            'Прошу рассчитать стоимость перевозки из Смоленска в Москву, груз паллеты 3 тонны, телефон +7 999 000 11 22',
        );

        $this->assertSame('Смоленска', $parsed['loading_location']);
        $this->assertSame('Москву', $parsed['unloading_location']);
        $this->assertSame('паллеты 3 тонны', $parsed['cargo']);
        $this->assertSame('+7 999 000 11 22', $parsed['phone']);
    }
}

<?php

namespace Tests\Unit;

use App\Services\SalesScripts\SalesScriptBodyPlaceholderService;
use Tests\TestCase;

class SalesScriptBodyPlaceholderServiceTest extends TestCase
{
    public function test_builds_capture_and_reference_segments(): void
    {
        $service = new SalesScriptBodyPlaceholderService;

        $segments = $service->buildSegments(
            '{client_name}, подскажите маршруты: {routes}. Мы возим по {routes}.',
            ['client_name', 'routes'],
            ['routes' => 'Москва — Казань'],
            ['client_name' => 'Имя', 'routes' => 'Маршруты'],
        );

        $this->assertSame('capture', $segments[0]['type']);
        $this->assertSame('client_name', $segments[0]['code']);
        $this->assertSame('capture', $segments[2]['type']);
        $this->assertSame('routes', $segments[2]['code']);
        $this->assertSame('reference', $segments[4]['type']);
        $this->assertSame('Москва — Казань', $segments[4]['value']);
    }

    public function test_segments_to_plain_text_substitutes_values(): void
    {
        $service = new SalesScriptBodyPlaceholderService;

        $segments = $service->buildSegments(
            'Здравствуйте, {client_name}!',
            ['client_name'],
            ['client_name' => 'Иван'],
            ['client_name' => 'Имя'],
        );

        $this->assertSame('Здравствуйте, Иван!', $service->segmentsToPlainText($segments));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SalesScripts\TrainerDialogHintService;
use Tests\TestCase;

class TrainerDialogHintServiceTest extends TestCase
{
    public function test_extracts_meaningful_terms(): void
    {
        $service = new TrainerDialogHintService;
        $terms = $service->extractTermsForTests(
            'Нам нужна ставка по маршруту Москва — Казань на завтра. Цена критична, сроки сжаты.',
        );

        $this->assertContains('нужна', $terms);
        $this->assertContains('ставка', $terms);
        $this->assertContains('маршруту', $terms);
        $this->assertContains('москва', $terms);
        $this->assertContains('казань', $terms);
        $this->assertContains('завтра', $terms);
        $this->assertContains('критична', $terms);
        $this->assertContains('сроки', $terms);
        $this->assertContains('сжаты', $terms);
    }
}

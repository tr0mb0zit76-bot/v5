<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\OrderIntakeLearnedPhrasesService;
use Tests\TestCase;

class OrderIntakeLearnedPhrasesServiceTest extends TestCase
{
    public function test_remembers_and_applies_phrase_for_user(): void
    {
        $user = User::factory()->create();
        $service = app(OrderIntakeLearnedPhrasesService::class);

        $remembered = $service->remember($user, 'оплата через месяц', '30 календарных дней', 'payment_terms');

        $this->assertTrue($remembered['ok']);

        $applied = $service->applyLearnedPhrases($user, 'Ставка 100000, оплата через месяц после выгрузки');

        $this->assertStringContainsString('30 календарных дней', $applied);
        $this->assertStringNotContainsString('оплата через месяц', mb_strtolower($applied));
    }
}

<?php

namespace Tests\Unit\Services\Checko;

use App\Models\Contractor;
use App\Services\Checko\CheckoDataNormalizer;
use App\Services\Checko\ContractorScoringCalculator;
use App\Services\Checko\ContractorScoringService;
use App\Services\ContractorCreditService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractorScoringServiceTest extends TestCase
{
    #[Test]
    public function it_returns_payload_when_checko_succeeds(): void
    {
        Config::set('checko.api_key', 'test-key');
        Config::set('checko.api_base', 'https://api.checko.ru/v2');

        Http::fake([
            'api.checko.ru/v2/*' => Http::response([
                'data' => [
                    'Статус' => 'Действующая',
                    'НаимСокр' => 'ООО Тест',
                ],
            ], 200),
        ]);

        $credit = $this->createMock(ContractorCreditService::class);
        $credit->method('currentDebtForContractor')->willReturn(0.0);
        $credit->method('isBlockedByDebtLimit')->willReturn(false);

        $service = new ContractorScoringService(
            $credit,
            new CheckoDataNormalizer,
            new ContractorScoringCalculator,
        );

        $contractor = new Contractor;
        $contractor->forceFill([
            'inn' => '7707083893',
            'stop_on_limit' => false,
            'debt_limit' => 500_000,
        ]);
        $contractor->id = 1;

        $payload = $service->buildPayload($contractor, true);

        $this->assertTrue($payload['ok']);
        $this->assertSame('7707083893', $payload['inn']);
        $this->assertArrayHasKey('score', $payload);
        $this->assertArrayHasKey('recommended_debt_limit_rub', $payload);
    }

    #[Test]
    public function it_returns_error_without_api_key(): void
    {
        Config::set('checko.api_key', '');

        $credit = $this->createMock(ContractorCreditService::class);
        $service = new ContractorScoringService(
            $credit,
            new CheckoDataNormalizer,
            new ContractorScoringCalculator,
        );

        $contractor = new Contractor;
        $contractor->forceFill([
            'inn' => '7707083893',
        ]);
        $contractor->id = 1;

        $payload = $service->buildPayload($contractor, true);

        $this->assertFalse($payload['ok']);
        $this->assertArrayHasKey('error', $payload);
    }
}

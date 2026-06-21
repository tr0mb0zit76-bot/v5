<?php

namespace Tests\Unit;

use App\Support\CurrencyDictionary;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderNormsPenaltiesValidationTest extends TestCase
{
    /**
     * @return array<string, array<int, string|Rule>>
     */
    private function normsRules(): array
    {
        $codes = CurrencyDictionary::allowedCodes();

        return [
            'financial_term.client_norms_penalties' => ['nullable', 'array'],
            'financial_term.client_norms_penalties.miss_amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.client_norms_penalties.miss_currency' => ['nullable', Rule::in($codes)],
            'financial_term.client_norms_penalties.downtime_amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.client_norms_penalties.downtime_currency' => ['nullable', Rule::in($codes)],
            'financial_term.client_norms_penalties.fine_amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.client_norms_penalties.fine_currency' => ['nullable', Rule::in($codes)],
            'financial_term.client_norms_penalties.penalty_terms' => ['nullable', 'string', 'max:2000'],
            'financial_term.client_norms_penalties.norm_loading_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'financial_term.client_norms_penalties.norm_customs_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'financial_term.client_norms_penalties.norm_unloading_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'financial_term.carrier_norms_by_leg' => ['nullable', 'array'],
            'financial_term.carrier_norms_by_leg.0.stage' => ['nullable', 'string', 'max:50'],
            'financial_term.carrier_norms_by_leg.0.miss_currency' => ['nullable', Rule::in($codes)],
        ];
    }

    #[Test]
    public function valid_client_and_carrier_norms_payload_passes(): void
    {
        $data = [
            'financial_term' => [
                'client_norms_penalties' => [
                    'miss_amount' => 1000.5,
                    'miss_currency' => 'RUB',
                    'downtime_amount' => 500,
                    'downtime_currency' => 'EUR',
                    'fine_amount' => 200,
                    'fine_currency' => 'USD',
                    'penalty_terms' => '0.1% в день',
                    'norm_loading_hours' => 24,
                    'norm_customs_hours' => 48,
                    'norm_unloading_hours' => 12,
                ],
                'carrier_norms_by_leg' => [
                    [
                        'stage' => 'Плечо 1',
                        'miss_currency' => 'RUB',
                    ],
                ],
            ],
        ];

        $validator = Validator::make($data, $this->normsRules());

        $this->assertTrue($validator->passes(), (string) $validator->errors());
    }

    #[Test]
    public function invalid_currency_on_client_norms_fails(): void
    {
        $data = [
            'financial_term' => [
                'client_norms_penalties' => [
                    'miss_currency' => 'XXX',
                ],
            ],
        ];

        $validator = Validator::make($data, $this->normsRules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('financial_term.client_norms_penalties.miss_currency', $validator->errors()->toArray());
    }
}

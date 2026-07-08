<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\LeadPerformerPayloadNormalizer;
use App\Support\LeadPrecalculationFreightAllocator;
use App\Support\LeadPrecalculationPayloadNormalizer;

final class LeadPrecalculationService
{
    public function __construct(
        private readonly ImportCostCalculatorService $importCostCalculator,
    ) {}

    /**
     * @param  array<string, mixed>|null  $precalculation
     * @return array<string, mixed>
     */
    public function normalize(?array $precalculation): array
    {
        return LeadPrecalculationPayloadNormalizer::normalize($precalculation);
    }

    /**
     * @param  array<string, mixed>  $precalculation
     * @return array<string, mixed>
     */
    public function calculate(array $precalculation): array
    {
        $normalized = LeadPrecalculationPayloadNormalizer::normalize($precalculation);

        $freightAllocation = LeadPrecalculationFreightAllocator::apply(
            $normalized['goods_lines'],
            $normalized['freight'],
        );

        $goodsResults = [];
        $goodsTotal = 0.0;
        $warnings = [];

        foreach ($freightAllocation['goods_lines'] as $line) {
            $lineResult = $this->calculateGoodsLine($line);
            $goodsResults[] = $lineResult;

            if (isset($lineResult['warning'])) {
                $warnings[] = $lineResult['warning'];
            }

            if (isset($lineResult['error'])) {
                $warnings[] = $lineResult['error'];
            }

            if (isset($lineResult['summary']['total_landed'])) {
                $goodsTotal += (float) $lineResult['summary']['total_landed'];
            }
        }

        $serviceResults = [];
        $servicesTotal = 0.0;

        foreach ($normalized['service_lines'] as $line) {
            $amountRub = $this->serviceLineAmountRub($line);
            $serviceResults[] = [
                'line_id' => $line['id'],
                'title' => $line['title'],
                'kind' => $line['kind'],
                'stage' => $line['stage'],
                'amount_rub' => $amountRub,
            ];

            if ($amountRub !== null) {
                $servicesTotal += $amountRub;
            }
        }

        $computed = [
            'goods_total' => round($goodsTotal, 0),
            'services_total' => round($servicesTotal, 0),
            'grand_total' => round($goodsTotal + $servicesTotal, 0),
            'freight_allocation' => $freightAllocation['allocation'],
            'goods_lines' => $goodsResults,
            'service_lines' => $serviceResults,
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'calculated_at' => now()->toIso8601String(),
        ];

        return [
            ...$normalized,
            'computed' => $computed,
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $performers
     * @return list<array<string, mixed>>
     */
    public function serviceLinesFromPerformers(?array $performers): array
    {
        $normalizedPerformers = LeadPerformerPayloadNormalizer::normalizeList($performers);
        $lines = [];

        foreach ($normalizedPerformers as $index => $performer) {
            if ($performer['estimated_cost'] === null) {
                continue;
            }

            $lines[] = LeadPrecalculationPayloadNormalizer::normalizeServiceLine([
                'id' => 'leg_service_'.($index + 1),
                'kind' => 'logistics',
                'title' => 'Логистика · '.$performer['stage'],
                'stage' => $performer['stage'],
                'amount' => $performer['estimated_cost'],
                'currency' => 'RUB',
            ], $index);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function calculateGoodsLine(array $line): array
    {
        $base = [
            'line_id' => $line['id'],
            'description' => $line['description'],
            'quantity' => $line['quantity'],
            'weight_kg' => $line['weight_kg'],
        ];

        if ($line['tn_ved_code'] === '') {
            return [
                ...$base,
                'warning' => 'Укажите код ТН ВЭД для строки '.($line['description'] ?: $line['id']),
            ];
        }

        if ($line['invoice_amount'] === null) {
            return [
                ...$base,
                'warning' => 'Укажите инвойсную стоимость для строки '.($line['description'] ?: $line['id']),
            ];
        }

        $payload = [
            'tn_ved_code' => $line['tn_ved_code'],
            'invoice_amount' => $line['invoice_amount'],
            'currency' => $line['currency'],
            'freight_to_border' => $line['freight_to_border'],
            'freight_after_border' => $line['freight_after_border'],
            'other_costs' => $line['other_costs'],
            'vehicle_age_years' => $line['vehicle_age_years'],
            'include_utilization_fee' => $line['include_utilization_fee'],
        ];

        if ($line['currency'] !== 'RUB') {
            if ($line['exchange_rate'] === null) {
                return [
                    ...$base,
                    'warning' => 'Укажите курс валюты для строки '.($line['description'] ?: $line['id']),
                ];
            }

            $payload['exchange_rate'] = $line['exchange_rate'];
        }

        if ($line['duty_percent_override'] !== null) {
            $payload['duty_percent_override'] = $line['duty_percent_override'];
        }

        if ($line['vat_percent_override'] !== null) {
            $payload['vat_percent_override'] = $line['vat_percent_override'];
        }

        $result = $this->importCostCalculator->calculate($payload);

        return [
            ...$base,
            ...$result,
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function serviceLineAmountRub(array $line): ?float
    {
        if ($line['amount'] === null) {
            return null;
        }

        $amount = (float) $line['amount'];

        if (($line['currency'] ?? 'RUB') === 'RUB') {
            return round($amount, 0);
        }

        return round($amount, 0);
    }
}

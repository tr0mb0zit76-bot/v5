<?php

namespace App\Services\Checko;

/**
 * Рекомендательная оценка v2: tier по масштабу + компоненты legal/capacity/relationship.
 */
class ContractorScoringCalculator
{
    public const MAX_RECOMMENDED_POSTPAYMENT_DAYS = 10;

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $internal
     * @return array<string, mixed>
     */
    public function calculate(array $normalized, array $internal): array
    {
        $debtLimitReached = (bool) ($internal['debt_limit_reached'] ?? false);
        $stopOnLimit = (bool) ($internal['stop_on_limit'] ?? false);
        $currentDebt = (float) ($internal['current_debt'] ?? 0);
        $debtLimit = $internal['debt_limit'];
        $debtLimit = is_numeric($debtLimit) ? (float) $debtLimit : null;

        $statusText = isset($normalized['status_text']) && is_string($normalized['status_text'])
            ? $normalized['status_text']
            : null;

        $egrStatus = $this->classifyEgrStatus($statusText);
        $tier = $this->resolveTier($normalized);
        $tierLabel = (string) config('contractor_scoring.tier_labels.'.$tier, $tier);

        $factors = [];

        $legalScore = $this->legalScore($normalized, $egrStatus, $factors);
        $capacityScore = $this->capacityScore($normalized, $tier, $factors);
        $relationshipScore = $this->relationshipScore($internal, $debtLimit, $currentDebt, $debtLimitReached, $stopOnLimit, $factors);

        $weights = config('contractor_scoring.component_weights');
        $composite = (int) round(
            $legalScore * ($weights['legal'] ?? 0.4)
            + $capacityScore * ($weights['capacity'] ?? 0.4)
            + $relationshipScore * ($weights['relationship'] ?? 0.2)
        );
        $composite = max(0, min(100, $composite));

        if ($debtLimitReached) {
            $composite = min($composite, 25);
        }

        if ($egrStatus === 'inactive') {
            $composite = min($composite, 18);
        }

        $grade = $this->gradeFromScore($composite);
        $recommendedDays = $this->recommendedPostpaymentDays($composite, $egrStatus, $debtLimitReached);

        $lastRevenueRub = isset($normalized['last_revenue_rub']) && is_numeric($normalized['last_revenue_rub'])
            ? (float) $normalized['last_revenue_rub']
            : null;

        $enforcementSum = (float) ($normalized['enforcement_sum_rub'] ?? 0);
        $enforcementCount = (int) ($normalized['enforcement_count'] ?? 0);

        $recommendedDebtLimitRub = $this->recommendedDebtLimitRubles(
            $tier,
            $composite,
            $egrStatus,
            $debtLimitReached,
            $lastRevenueRub,
            $enforcementSum,
            $enforcementCount,
        );

        $summary = $this->buildSummary($grade, $tierLabel, $recommendedDays, $egrStatus, $debtLimitReached);

        return [
            'score' => $composite,
            'grade' => $grade,
            'tier' => $tier,
            'tier_label' => $tierLabel,
            'components' => [
                'legal' => $legalScore,
                'capacity' => $capacityScore,
                'relationship' => $relationshipScore,
                'composite' => $composite,
            ],
            'scoring_model_version' => (string) config('contractor_scoring.model_version'),
            'recommended_postpayment_days' => $recommendedDays,
            'recommended_debt_limit_rub' => $recommendedDebtLimitRub,
            'factors' => $factors,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function resolveTier(array $normalized): string
    {
        $revenue = isset($normalized['last_revenue_rub']) && is_numeric($normalized['last_revenue_rub'])
            ? (float) $normalized['last_revenue_rub']
            : null;

        $assets = isset($normalized['last_assets_rub']) && is_numeric($normalized['last_assets_rub'])
            ? (float) $normalized['last_assets_rub']
            : null;

        $scale = max($revenue ?? 0.0, $assets ?? 0.0);

        if ($scale <= 0) {
            return 'micro';
        }

        $thresholds = config('contractor_scoring.tier_thresholds_rub');

        if ($scale >= ($thresholds['enterprise'] ?? 5_000_000_000)) {
            return 'enterprise';
        }

        if ($scale >= ($thresholds['large'] ?? 1_000_000_000)) {
            return 'large';
        }

        if ($scale >= ($thresholds['mid'] ?? 200_000_000)) {
            return 'mid';
        }

        if ($scale >= ($thresholds['small'] ?? 20_000_000)) {
            return 'small';
        }

        return 'micro';
    }

    /**
     * @param  list<string>  $factors
     */
    private function legalScore(array $normalized, string $egrStatus, array &$factors): int
    {
        $score = 50;

        if ($egrStatus === 'inactive') {
            $score = 5;
        } elseif ($egrStatus === 'active') {
            $score += 35;
        } else {
            $score += 15;
        }

        $enforcementCount = (int) ($normalized['enforcement_count'] ?? 0);
        $enforcementSum = (float) ($normalized['enforcement_sum_rub'] ?? 0);
        $defendantCases = (int) ($normalized['defendant_cases'] ?? 0);

        if ($enforcementCount === 0 && $enforcementSum < 1.0) {
            $score += 10;
        } else {
            $penalty = min(45, 8 + (int) min(28, max(0, log10(max($enforcementSum, 1)) * 6)) + min(12, $enforcementCount * 3));
            $score -= $penalty;
            $factors[] = 'Исполнительные производства: '.(string) $enforcementCount.' шт., сумма ~ '.number_format($enforcementSum, 0, '.', ' ').' ₽.';
        }

        if ($defendantCases === 0) {
            $score += 5;
        } else {
            $score -= min(25, 8 + (int) floor($defendantCases / 2));
            $factors[] = 'Судебные дела как ответчик: '.(string) $defendantCases.' — повышает риск споров и взысканий.';
        }

        if ((bool) ($normalized['bankruptcy_risk_flag'] ?? false)) {
            $score -= 20;
            $factors[] = 'Признаки процедуры банкротства или наблюдения в данных Checko.';
        }

        return max(0, min(100, $score));
    }

    /**
     * @param  list<string>  $factors
     */
    private function capacityScore(array $normalized, string $tier, array &$factors): int
    {
        $score = 40;

        $tierBonus = match ($tier) {
            'enterprise' => 25,
            'large' => 20,
            'mid' => 15,
            'small' => 10,
            default => 0,
        };
        $score += $tierBonus;

        $financesAvailable = (bool) ($normalized['finances_available'] ?? false);
        $lastProfitPositive = $normalized['last_profit_positive'];

        if ($financesAvailable) {
            if ($lastProfitPositive === true) {
                $score += 15;
                $factors[] = 'По данным отчётности (последний доступный год) прибыль неотрицательна.';
            } elseif ($lastProfitPositive === false) {
                $score -= 15;
                $factors[] = 'По данным отчётности (последний доступный год) убыток — снижает устойчивость.';
            }
        } else {
            $score -= 8;
            $factors[] = 'Нет уверенной финансовой отчётности в ответе API — консервативная скидка.';
        }

        $revenueTrend = $normalized['revenue_trend'] ?? null;
        if ($revenueTrend === 'growing') {
            $score += 8;
            $factors[] = 'Выручка растёт относительно предыдущего года в данных Checko.';
        } elseif ($revenueTrend === 'declining') {
            $score -= 10;
            $factors[] = 'Выручка снижается относительно предыдущего года.';
        }

        $ageYears = isset($normalized['company_age_years']) && is_numeric($normalized['company_age_years'])
            ? (int) $normalized['company_age_years']
            : null;

        if ($ageYears !== null) {
            if ($ageYears >= 5) {
                $score += 8;
            } elseif ($ageYears < 2) {
                $score -= 6;
                $factors[] = 'Компания моложе 2 лет — повышенный риск.';
            }
        }

        return max(0, min(100, $score));
    }

    /**
     * @param  array<string, mixed>  $internal
     * @param  list<string>  $factors
     */
    private function relationshipScore(
        array $internal,
        ?float $debtLimit,
        float $currentDebt,
        bool $debtLimitReached,
        bool $stopOnLimit,
        array &$factors,
    ): int {
        $score = 70;

        if ($debtLimitReached) {
            $score = 10;
            $factors[] = 'По данным CRM лимит задолженности достигнут — отсрочка не рекомендуется до погашения.';

            return $score;
        }

        if ($debtLimit !== null && $debtLimit > 0) {
            $utilization = $currentDebt / $debtLimit;
            if ($utilization >= 0.95) {
                $score -= 30;
                $factors[] = 'Задолженность близка к внутреннему лимиту (высокая утилизация).';
            } elseif ($utilization >= 0.75) {
                $score -= 15;
                $factors[] = 'Задолженность существенная относительно внутреннего лимита.';
            } elseif ($utilization < 0.25) {
                $score += 10;
            }
        }

        if ($stopOnLimit) {
            $score -= 8;
            $factors[] = 'В карточке включён останов при лимите — политика компании требует жёсткого контроля.';
        }

        return max(0, min(100, $score));
    }

    /**
     * @return 'active'|'inactive'|'unknown'
     */
    public function classifyEgrStatus(?string $status): string
    {
        if ($status === null || trim($status) === '') {
            return 'unknown';
        }

        $trimmed = trim($status);
        $statusLower = mb_strtolower($trimmed);

        if ($this->isEgrStatusMetaOnly($trimmed)) {
            return 'unknown';
        }

        if (str_contains($statusLower, 'ликвидир')) {
            return 'inactive';
        }

        if (str_contains($statusLower, 'банкрот')) {
            return 'inactive';
        }

        if (preg_match('/не\s+прекращ/u', $statusLower) === 1) {
            return 'active';
        }

        if (str_contains($statusLower, 'прекращ')) {
            return 'inactive';
        }

        if (preg_match('/не\s+исключ/u', $statusLower) === 1) {
            return 'active';
        }

        if (str_contains($statusLower, 'исключ')) {
            return 'inactive';
        }

        if (str_contains($statusLower, 'не действ')) {
            return 'inactive';
        }

        if (preg_match('/в\s+процессе\s+(?:ликвидации|реорганизации|банкротства|исключения)/u', $statusLower) === 1) {
            return 'inactive';
        }

        if (str_contains($statusLower, 'действ')) {
            return 'active';
        }

        return 'unknown';
    }

    /**
     * @deprecated Используйте {@see classifyEgrStatus()}.
     */
    public function isCompanyActiveByEgrStatus(?string $status): bool
    {
        return $this->classifyEgrStatus($status) === 'active';
    }

    private function isEgrStatusMetaOnly(string $value): bool
    {
        $t = mb_strtolower(trim($value));

        return in_array($t, ['ok', 'success', 'error', 'true', 'false'], true)
            || preg_match('/^\d{3}$/', $t) === 1;
    }

    private function gradeFromScore(int $score): string
    {
        if ($score >= 78) {
            return 'A';
        }

        if ($score >= 62) {
            return 'B';
        }

        if ($score >= 45) {
            return 'C';
        }

        return 'D';
    }

    private function recommendedDebtLimitRubles(
        string $tier,
        int $composite,
        string $egrStatus,
        bool $debtLimitReached,
        ?float $lastRevenueRub,
        float $enforcementSum,
        int $enforcementCount,
    ): int {
        if ($egrStatus === 'inactive' || $debtLimitReached) {
            return 0;
        }

        $baseLimits = config('contractor_scoring.tier_base_debt_limit_rub');
        $base = (int) ($baseLimits[$tier] ?? 150_000);

        $healthMultiplier = max(0.25, $composite / 100);
        $cap = (int) round($base * $healthMultiplier);

        $mult = 1.0;
        if ($enforcementSum >= 5_000_000 || $enforcementCount >= 5) {
            $mult = 0.25;
        } elseif ($enforcementSum >= 1_000_000 || $enforcementCount >= 3) {
            $mult = 0.5;
        } elseif ($enforcementSum >= 300_000 || $enforcementCount >= 2) {
            $mult = 0.75;
        }

        $cap = (int) round($cap * $mult);

        $revenueCapRatio = (float) config('contractor_scoring.revenue_cap_ratio', 0.08);
        if ($lastRevenueRub !== null && $lastRevenueRub > 0) {
            $fromRevenue = $lastRevenueRub * $revenueCapRatio;
            $cap = (int) min($cap, $fromRevenue);
        }

        $step = (int) config('contractor_scoring.limit_round_step_rub', 50_000);
        if ($step > 0) {
            $cap = (int) (round($cap / $step) * $step);
        }

        return max(0, $cap);
    }

    private function recommendedPostpaymentDays(int $score, string $egrStatus, bool $debtLimitReached): int
    {
        if ($debtLimitReached || $egrStatus === 'inactive') {
            return 0;
        }

        $maxDays = (int) config('contractor_scoring.max_recommended_postpayment_days', self::MAX_RECOMMENDED_POSTPAYMENT_DAYS);

        if ($score >= 82) {
            return $maxDays;
        }

        if ($score >= 72) {
            return 7;
        }

        if ($score >= 58) {
            return 5;
        }

        if ($score >= 42) {
            return 3;
        }

        return 0;
    }

    private function buildSummary(string $grade, string $tierLabel, int $days, string $egrStatus, bool $debtLimitReached): string
    {
        if ($debtLimitReached || $egrStatus === 'inactive') {
            return '';
        }

        return 'Ориентир для переговоров; не заменяет финансовую отчётность и договор кредитной линии.';
    }
}

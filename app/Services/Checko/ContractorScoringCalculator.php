<?php

namespace App\Services\Checko;

/**
 * Рекомендательная оценка для внутреннего CRM (не кредитный рейтинг банка).
 * Отсрочка ограничена сверху (см. maxRecommendedDays).
 */
class ContractorScoringCalculator
{
    public const MAX_RECOMMENDED_POSTPAYMENT_DAYS = 10;

    /**
     * @param  array<string, mixed>  $normalized  из CheckoDataNormalizer::normalize()
     * @param  array<string, mixed>  $internal
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

        $factors = [];

        $score = 35;

        if ($egrStatus === 'inactive') {
            $score = min($score, 18);
            $factors[] = 'По данным ЕГРЮЛ/статусу компания не выглядит надёжной для отсрочки (ликвидация, банкротство, исключение и т.п.).';
        } elseif ($egrStatus === 'active') {
            $score += 22;
            $factors[] = 'Юридический статус допускает работу по отсрочке (действующая организация).';
        } else {
            $score += 8;
            $factors[] = 'Статус ЕГРЮЛ в ответе Checko не распознан однозначно — автоматически не отнесено к ликвидации/исключению; при сомнениях сверьтесь с карточкой на checko.ru.';
        }

        $enforcementCount = (int) ($normalized['enforcement_count'] ?? 0);
        $enforcementSum = (float) ($normalized['enforcement_sum_rub'] ?? 0);
        $defendantCases = (int) ($normalized['defendant_cases'] ?? 0);
        $plaintiffCases = (int) ($normalized['plaintiff_cases'] ?? 0);

        if ($enforcementCount === 0 && $enforcementSum < 1.0) {
            $score += 12;
            $factors[] = 'По данным ФССП не выявлено исполнительных производств (или сумма неизвестна).';
        } else {
            $penalty = 8;
            if ($enforcementSum >= 5_000_000) {
                $penalty += 28;
            } elseif ($enforcementSum >= 1_000_000) {
                $penalty += 18;
            } elseif ($enforcementSum >= 300_000) {
                $penalty += 12;
            }

            if ($enforcementCount >= 5) {
                $penalty += 12;
            } elseif ($enforcementCount >= 2) {
                $penalty += 6;
            }

            $score -= min($penalty, 45);
            $factors[] = 'Исполнительные производства: '.(string) $enforcementCount.' шт., сумма ~ '.number_format($enforcementSum, 0, '.', ' ').' ₽ (рост риска неплатежа).';
        }

        if ($defendantCases === 0) {
            $score += 6;
            $factors[] = 'Судебные дела как ответчик не выявлены (по выборке Checko).';
        } else {
            $casePenalty = min(28, 10 + (int) floor($defendantCases / 3) * 2);
            $score -= $casePenalty;
            $factors[] = 'Судебные дела как ответчик: '.(string) $defendantCases.' — повышает риск споров и взысканий.';
        }

        if ($plaintiffCases > 20) {
            $score -= 4;
            $factors[] = 'Много активных судебных требований как истец — оцените деловую агрессивность/контрагентскую среду.';
        }

        $financesAvailable = (bool) ($normalized['finances_available'] ?? false);
        $lastProfitPositive = $normalized['last_profit_positive'];

        if ($financesAvailable) {
            if ($lastProfitPositive === true) {
                $score += 8;
                $factors[] = 'По данным отчётности (последний доступный год) прибыль неотрицательна.';
            } elseif ($lastProfitPositive === false) {
                $score -= 12;
                $factors[] = 'По данным отчётности (последний доступный год) убыток — снижает кредитную устойчивость.';
            } else {
                $factors[] = 'Финансовая отчётность есть, но прибыль/убыток не распознаны автоматически — проверьте вручную.';
            }
        } else {
            $score -= 4;
            $factors[] = 'Нет уверенной финансовой отчётности в ответе API — консервативная скидка к оценке.';
        }

        if ($debtLimit !== null && $debtLimit > 0) {
            $utilization = $currentDebt / $debtLimit;
            if ($utilization >= 0.95) {
                $score -= 12;
                $factors[] = 'Задолженность близка к внутреннему лимиту (высокая утилизация лимита).';
            } elseif ($utilization >= 0.75) {
                $score -= 6;
                $factors[] = 'Задолженность существенная относительно внутреннего лимита.';
            }
        }

        if ($stopOnLimit) {
            $score -= 4;
            $factors[] = 'В карточке включён останов при лимите — политика компании требует жёсткого контроля.';
        }

        if ($debtLimitReached) {
            $score = min($score, 25);
            $factors[] = 'По данным CRM лимит задолженности достигнут — отсрочка не рекомендуется до погашения.';
        }

        $score = (int) max(0, min(100, round($score)));

        $grade = $this->gradeFromScore($score);

        $recommendedDays = $this->recommendedPostpaymentDays($score, $egrStatus, $debtLimitReached);

        $lastRevenueRub = isset($normalized['last_revenue_rub']) && is_numeric($normalized['last_revenue_rub'])
            ? (float) $normalized['last_revenue_rub']
            : null;

        $recommendedDebtLimitRub = $this->recommendedDebtLimitRubles(
            $score,
            $egrStatus,
            $debtLimitReached,
            $lastRevenueRub,
            $enforcementSum,
            $enforcementCount,
        );

        $summary = $this->buildSummary($grade, $recommendedDays, $egrStatus, $debtLimitReached);

        return [
            'score' => $score,
            'grade' => $grade,
            'recommended_postpayment_days' => $recommendedDays,
            'recommended_debt_limit_rub' => $recommendedDebtLimitRub,
            'factors' => $factors,
            'summary' => $summary,
        ];
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
     * Эвристика по строке статуса ЕГРЮЛ / карточки ФНС.
     * «прекращ»/«исключ» дают ложные срабатывания на «деятельность не прекращена», «не исключён».
     *
     * @deprecated Используйте {@see classifyEgrStatus()}; true только при явном «действующая» и т.п.
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

    /**
     * Ориентир по лимиту открытой задолженности (₽): класс оценки, исполнительные производства,
     * при наличии выручки — не выше ~8% от последней выручки в данных Checko.
     */
    private function recommendedDebtLimitRubles(
        int $score,
        string $egrStatus,
        bool $debtLimitReached,
        ?float $lastRevenueRub,
        float $enforcementSum,
        int $enforcementCount,
    ): int {
        if ($egrStatus === 'inactive' || $debtLimitReached) {
            return 0;
        }

        $cap = match (true) {
            $score >= 82 => 5_000_000,
            $score >= 72 => 3_000_000,
            $score >= 62 => 1_500_000,
            $score >= 52 => 800_000,
            $score >= 42 => 400_000,
            default => 0,
        };

        if ($cap === 0) {
            return 0;
        }

        $mult = 1.0;
        if ($enforcementSum >= 5_000_000 || $enforcementCount >= 5) {
            $mult = 0.25;
        } elseif ($enforcementSum >= 1_000_000 || $enforcementCount >= 3) {
            $mult = 0.5;
        } elseif ($enforcementSum >= 300_000 || $enforcementCount >= 2) {
            $mult = 0.75;
        }

        $cap = (int) round($cap * $mult);

        if ($lastRevenueRub !== null && $lastRevenueRub > 0) {
            $fromRevenue = $lastRevenueRub * 0.08;
            $cap = (int) min($cap, $fromRevenue);
        }

        $cap = (int) (round($cap / 50_000) * 50_000);

        return max(0, $cap);
    }

    private function recommendedPostpaymentDays(int $score, string $egrStatus, bool $debtLimitReached): int
    {
        if ($debtLimitReached || $egrStatus === 'inactive') {
            return 0;
        }

        if ($score >= 82) {
            return self::MAX_RECOMMENDED_POSTPAYMENT_DAYS;
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

    private function buildSummary(string $grade, int $days, string $egrStatus, bool $debtLimitReached): string
    {
        if ($debtLimitReached) {
            return 'Лимит в CRM исчерпан: отсрочку не рекомендуем, работайте по предоплате или после погашения.';
        }

        if ($egrStatus === 'inactive') {
            return 'Юридический статус не позволяет отсрочку: требуется предоплата или отказ от сделки.';
        }

        return 'Класс '.$grade.'. Рекомендуемая отсрочка (ориентир для переговоров): до '.$days.' календарных дней. Оценка не заменяет финансовую отчётность и договор кредитной линии.';
    }
}

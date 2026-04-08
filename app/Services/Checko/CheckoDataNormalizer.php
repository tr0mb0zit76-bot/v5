<?php

namespace App\Services\Checko;

use Illuminate\Support\Arr;

/**
 * Приводит «сырые» ответы Checko к компактной форме для скоринга.
 * Структура API может отличаться — извлечение максимально защитное.
 */
class CheckoDataNormalizer
{
    /**
     * @param  array<string, array{ok: bool, status: int, body: array<string, mixed>|null}>  $bundle
     * @return array<string, mixed>
     */
    public function normalize(array $bundle): array
    {
        $companyBody = $bundle['company']['body'] ?? null;
        $companyBody = is_array($companyBody) ? $companyBody : [];

        $statusText = $this->extractStatusText($companyBody);
        $companyName = $this->extractCompanyName($companyBody);

        $financesBody = $bundle['finances']['body'] ?? null;
        $financesBody = is_array($financesBody) ? $financesBody : [];

        $enforcementsBody = $bundle['enforcements']['body'] ?? null;
        $enforcementsBody = is_array($enforcementsBody) ? $enforcementsBody : [];

        $defBody = $bundle['legal_defendant']['body'] ?? null;
        $defBody = is_array($defBody) ? $defBody : [];

        $plBody = $bundle['legal_plaintiff']['body'] ?? null;
        $plBody = is_array($plBody) ? $plBody : [];

        $fin = $this->extractFinancesHints($financesBody);
        $enf = $this->extractEnforcementStats($enforcementsBody);

        return [
            'company_name' => $companyName,
            'status_text' => $statusText,
            'finances_available' => $fin['available'],
            'last_profit_positive' => $fin['last_profit_positive'],
            'last_revenue_rub' => $fin['last_revenue_rub'],
            'enforcement_count' => $enf['count'],
            'enforcement_sum_rub' => $enf['sum_rub'],
            'defendant_cases' => $this->countLegalCases($defBody),
            'plaintiff_cases' => $this->countLegalCases($plBody),
        ];
    }

    /**
     * @param  array<string, mixed>  $companyBody
     */
    private function extractStatusText(array $companyBody): ?string
    {
        $candidates = [
            'data.Статус',
            'data.СтатусОрг',
            'data.СтатусКомпании',
            'data.status',
            'data.meta.status',
            'meta.status',
        ];

        foreach ($candidates as $path) {
            $v = data_get($companyBody, $path);
            if (! is_string($v) || trim($v) === '') {
                continue;
            }

            if ($this->isApiMetaStatusLabel($v)) {
                continue;
            }

            return $v;
        }

        $data = data_get($companyBody, 'data');
        if (is_array($data)) {
            foreach (['Статус', 'status', 'СтатусКомпании', 'СтатусОрг', 'СтатусНаДату', 'СтатусЮЛ'] as $k) {
                if (! isset($data[$k]) || ! is_string($data[$k]) || trim($data[$k]) === '') {
                    continue;
                }

                if ($this->isApiMetaStatusLabel($data[$k])) {
                    continue;
                }

                return $data[$k];
            }

            $deep = $this->findStatusStringInNestedArray($data);
            if ($deep !== null) {
                return $deep;
            }
        }

        $deepRoot = $this->findStatusStringInNestedArray($companyBody);
        if ($deepRoot !== null) {
            return $deepRoot;
        }

        return null;
    }

    /**
     * Рекурсивный поиск строки статуса по ключам вроде «Статус*», «status», «состояние» (структура Checko может отличаться).
     *
     * @param  array<string, mixed>  $node
     */
    private function findStatusStringInNestedArray(array $node, int $depth = 0): ?string
    {
        if ($depth > 5) {
            return null;
        }

        foreach ($node as $key => $value) {
            if (is_string($key) && is_string($value) && trim($value) !== '') {
                $keyLower = mb_strtolower($key);
                $looksLikeStatusKey = str_contains($keyLower, 'статус')
                    || $keyLower === 'status'
                    || str_contains($keyLower, 'состояние');

                if ($looksLikeStatusKey && ! $this->isApiMetaStatusLabel($value)) {
                    return $value;
                }
            }

            if (is_array($value)) {
                $found = $this->findStatusStringInNestedArray($value, $depth + 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Checko кладёт в meta.status служебные значения (ok/success), это не статус ЕГРЮЛ.
     */
    private function isApiMetaStatusLabel(string $v): bool
    {
        $t = mb_strtolower(trim($v));

        return in_array($t, ['ok', 'success', 'error', 'true', 'false'], true)
            || preg_match('/^\d{3}$/', $t) === 1;
    }

    /**
     * @param  array<string, mixed>  $companyBody
     */
    private function extractCompanyName(array $companyBody): ?string
    {
        $paths = [
            'data.НаимСокр',
            'data.НаимПолн',
            'data.name',
            'data.meta.name',
        ];

        foreach ($paths as $path) {
            $v = data_get($companyBody, $path);
            if (is_string($v) && trim($v) !== '') {
                return $v;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $financesBody
     * @return array{available: bool, last_profit_positive: bool|null, last_revenue_rub: float|null}
     */
    private function extractFinancesHints(array $financesBody): array
    {
        $rows = data_get($financesBody, 'data');
        if (! is_array($rows)) {
            return ['available' => false, 'last_profit_positive' => null, 'last_revenue_rub' => null];
        }

        $list = array_is_list($rows) ? $rows : Arr::wrap($rows);
        if ($list === []) {
            return ['available' => false, 'last_profit_positive' => null, 'last_revenue_rub' => null];
        }

        $bestYear = null;
        $bestRow = null;

        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }

            $year = $row['year'] ?? $row['Год'] ?? $row['Период'] ?? null;
            $y = is_numeric($year) ? (int) $year : null;
            if ($y === null) {
                continue;
            }

            if ($bestYear === null || $y > $bestYear) {
                $bestYear = $y;
                $bestRow = $row;
            }
        }

        if ($bestRow === null) {
            return ['available' => false, 'last_profit_positive' => null, 'last_revenue_rub' => null];
        }

        $profit = $this->findNumericKey($bestRow, ['profit', 'Прибыль', 'ЧистаяПрибыль', 'ФинРез']);
        $revenue = $this->findNumericKey($bestRow, ['revenue', 'Выручка', 'Оборот']);

        $profitPositive = null;
        if ($profit !== null) {
            $profitPositive = $profit >= 0.0;
        }

        return [
            'available' => true,
            'last_profit_positive' => $profitPositive,
            'last_revenue_rub' => $revenue,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function findNumericKey(array $row, array $keys): ?float
    {
        foreach ($keys as $k) {
            if (! array_key_exists($k, $row)) {
                continue;
            }

            $v = $row[$k];
            if (is_numeric($v)) {
                return (float) $v;
            }

            if (is_string($v)) {
                $normalized = preg_replace('/[^\d\-.,]/u', '', $v);
                $normalized = str_replace(',', '.', (string) $normalized);
                if ($normalized !== '' && is_numeric($normalized)) {
                    return (float) $normalized;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $enforcementsBody
     * @return array{count: int, sum_rub: float}
     */
    private function extractEnforcementStats(array $enforcementsBody): array
    {
        $total = (int) data_get($enforcementsBody, 'meta.total', 0);
        $data = data_get($enforcementsBody, 'data');

        $count = 0;
        $sum = 0.0;

        if (is_array($data)) {
            if (array_is_list($data)) {
                $count = count($data);
                foreach ($data as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $s = $this->findNumericKey($row, ['sum', 'Сумма', 'amount', 'Debt']);
                    if ($s !== null) {
                        $sum += abs($s);
                    }
                }
            } else {
                $count = $total > 0 ? $total : 0;
            }
        } elseif ($total > 0) {
            $count = $total;
        }

        return ['count' => $count, 'sum_rub' => $sum];
    }

    /**
     * @param  array<string, mixed>  $legalBody
     */
    private function countLegalCases(array $legalBody): int
    {
        $data = data_get($legalBody, 'data');
        if (is_array($data) && array_is_list($data)) {
            return count($data);
        }

        $total = data_get($legalBody, 'meta.total');

        return is_numeric($total) ? (int) $total : 0;
    }
}

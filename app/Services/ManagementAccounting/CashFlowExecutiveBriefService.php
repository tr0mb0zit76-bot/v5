<?php

declare(strict_types=1);

namespace App\Services\ManagementAccounting;

use App\Models\Contractor;
use App\Models\ManagementStatementLine;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Support\RoleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Комплексный CFO-бриф по кассе: чистый ДДС по выпискам + ДЗ/КЗ CRM + платежи без заказа.
 */
final class CashFlowExecutiveBriefService
{
    private const TOP_LIMIT = 10;

    private const OUTFLOW_WITHOUT_ORDER_LIMIT = 15;

    private const AR_AP_LIMIT = 12;

    /**
     * @return array<string, mixed>
     */
    public function brief(User $user, ?string $fromDate = null, ?string $toDate = null): array
    {
        if (! RoleAccess::canAccessManagementAccounting($user)) {
            return [
                'available' => false,
                'message' => 'Нет доступа к управленческому учёту.',
            ];
        }

        if (! Schema::hasTable('management_statement_lines')) {
            return [
                'available' => false,
                'message' => 'Таблица выписок недоступна.',
            ];
        }

        $from = $this->resolveFromDate($fromDate);
        $to = $this->resolveToDate($toDate);

        if ($to->lt($from)) {
            return [
                'available' => false,
                'message' => 'Дата окончания периода раньше даты начала.',
            ];
        }

        $lines = ManagementStatementLine::query()
            ->whereDate('operation_date', '>=', $from->toDateString())
            ->whereDate('operation_date', '<=', $to->toDateString())
            ->get([
                'id',
                'operation_date',
                'direction',
                'amount',
                'description',
                'status',
                'allocation_order_id',
                'bank_account_id',
            ]);

        $totals = $this->buildTotals($lines);
        $byMonth = $this->buildByMonth($lines);
        $counterparties = $this->buildCounterpartyTops($lines);
        $outflowStructure = $this->buildOutflowStructure($lines);
        $pending = $this->buildPending($lines);
        $withoutOrder = $this->buildOutflowsWithoutOrder($lines);
        $receivables = $this->buildReceivablesPayables($from, 'customer');
        $payables = $this->buildReceivablesPayables($from, 'carrier');
        $headline = $this->buildHeadline($totals, $pending, $receivables, $payables);
        $actions = $this->buildActions($totals, $pending, $withoutOrder, $receivables, $payables, $byMonth);

        return [
            'available' => true,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'lines_count' => $lines->count(),
            ],
            'executive_headline' => $headline,
            'cash_flow' => $totals,
            'by_month' => $byMonth,
            'top_in_counterparties' => $counterparties['in'],
            'top_out_counterparties' => $counterparties['out'],
            'outflow_structure' => $outflowStructure,
            'pending' => $pending,
            'outflows_without_crm_order' => $withoutOrder,
            'accounts_receivable' => $receivables,
            'accounts_payable' => $payables,
            'diagnosis' => $this->buildDiagnosis($totals, $receivables, $payables, $pending, $outflowStructure),
            'recommended_actions' => $actions,
            'methodology' => [
                'cash_flow' => 'Сумма direction in/out по management_statement_lines за период (не банковский остаток на дату).',
                'counterparties' => 'Имя до первого «/» в назначении платежа.',
                'ar_ap' => 'payment_schedules.remaining_amount > 0 по заказам с created_at >= from (party customer/carrier).',
                'without_order' => 'Исходящие с allocation_order_id IS NULL (включая pending и разнесённые на статью без заявки).',
            ],
        ];
    }

    private function resolveFromDate(?string $fromDate): CarbonImmutable
    {
        if (is_string($fromDate) && $fromDate !== '') {
            return CarbonImmutable::parse($fromDate)->startOfDay();
        }

        $configured = config('management_accounting.executive_brief_default_from');
        if (is_string($configured) && $configured !== '') {
            return CarbonImmutable::parse($configured)->startOfDay();
        }

        if (Schema::hasTable('orders')) {
            $minCreated = Order::query()->min('created_at');
            if (is_string($minCreated) && $minCreated !== '') {
                return CarbonImmutable::parse($minCreated)->startOfDay();
            }
        }

        $minOp = ManagementStatementLine::query()->min('operation_date');
        if (is_string($minOp) && $minOp !== '') {
            return CarbonImmutable::parse($minOp)->startOfDay();
        }

        return CarbonImmutable::now()->subMonths(4)->startOfDay();
    }

    private function resolveToDate(?string $toDate): CarbonImmutable
    {
        if (is_string($toDate) && $toDate !== '') {
            return CarbonImmutable::parse($toDate)->endOfDay();
        }

        $maxOp = ManagementStatementLine::query()->max('operation_date');
        if (is_string($maxOp) && $maxOp !== '') {
            return CarbonImmutable::parse($maxOp)->endOfDay();
        }

        return CarbonImmutable::now()->endOfDay();
    }

    /**
     * @param  Collection<int, ManagementStatementLine>  $lines
     * @return array{in: float, out: float, net: float}
     */
    private function buildTotals($lines): array
    {
        $in = 0.0;
        $out = 0.0;

        foreach ($lines as $line) {
            $amount = (float) $line->amount;
            if ($line->direction === 'in') {
                $in += $amount;
            } else {
                $out += $amount;
            }
        }

        return [
            'in' => round($in, 2),
            'out' => round($out, 2),
            'net' => round($in - $out, 2),
        ];
    }

    /**
     * @param  Collection<int, ManagementStatementLine>  $lines
     * @return list<array{month: string, in: float, out: float, net: float}>
     */
    private function buildByMonth($lines): array
    {
        $map = [];

        foreach ($lines as $line) {
            $ym = $line->operation_date?->format('Y-m') ?? 'unknown';
            if (! isset($map[$ym])) {
                $map[$ym] = ['in' => 0.0, 'out' => 0.0];
            }
            $amount = (float) $line->amount;
            if ($line->direction === 'in') {
                $map[$ym]['in'] += $amount;
            } else {
                $map[$ym]['out'] += $amount;
            }
        }

        ksort($map);
        $rows = [];
        foreach ($map as $ym => $vals) {
            $rows[] = [
                'month' => $ym,
                'in' => round($vals['in'], 2),
                'out' => round($vals['out'], 2),
                'net' => round($vals['in'] - $vals['out'], 2),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, ManagementStatementLine>  $lines
     * @return array{in: list<array{name: string, amount: float}>, out: list<array{name: string, amount: float}>}
     */
    private function buildCounterpartyTops($lines): array
    {
        $in = [];
        $out = [];

        foreach ($lines as $line) {
            $name = $this->counterpartyFromDescription((string) $line->description);
            $amount = (float) $line->amount;
            if ($line->direction === 'in') {
                $in[$name] = ($in[$name] ?? 0.0) + $amount;
            } else {
                $out[$name] = ($out[$name] ?? 0.0) + $amount;
            }
        }

        return [
            'in' => $this->topAmountMap($in, self::TOP_LIMIT),
            'out' => $this->topAmountMap($out, self::TOP_LIMIT),
        ];
    }

    /**
     * @param  array<string, float>  $map
     * @return list<array{name: string, amount: float}>
     */
    private function topAmountMap(array $map, int $limit): array
    {
        arsort($map);
        $rows = [];
        $i = 0;
        foreach ($map as $name => $amount) {
            $rows[] = ['name' => $name, 'amount' => round($amount, 2)];
            if (++$i >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, ManagementStatementLine>  $lines
     * @return list<array{bucket: string, amount: float, share_pct: float, lines: int}>
     */
    private function buildOutflowStructure($lines): array
    {
        $buckets = [
            'carriers_logistics' => 0.0,
            'leasing' => 0.0,
            'fuel' => 0.0,
            'taxes' => 0.0,
            'internal_transfers' => 0.0,
            'bank' => 0.0,
            'office_it' => 0.0,
            'payroll_individuals' => 0.0,
            'insurance_platon' => 0.0,
            'other' => 0.0,
        ];
        $counts = array_fill_keys(array_keys($buckets), 0);

        foreach ($lines as $line) {
            if ($line->direction !== 'out') {
                continue;
            }
            $bucket = $this->classifyOutflow((string) $line->description);
            $buckets[$bucket] += (float) $line->amount;
            $counts[$bucket]++;
        }

        $total = array_sum($buckets);
        $labels = [
            'carriers_logistics' => 'Перевозчики / логистика',
            'leasing' => 'Лизинг',
            'fuel' => 'Топливо',
            'taxes' => 'Налоги',
            'internal_transfers' => 'Внутригрупповые / переводы',
            'bank' => 'Банк',
            'office_it' => 'Офис / ИТ / связь',
            'payroll_individuals' => 'Подотчёт / физлица',
            'insurance_platon' => 'Страхование / Платон',
            'other' => 'Прочее',
        ];

        $rows = [];
        foreach ($buckets as $key => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $rows[] = [
                'bucket' => $key,
                'label' => $labels[$key] ?? $key,
                'amount' => round($amount, 2),
                'share_pct' => $total > 0 ? round(($amount / $total) * 100, 1) : 0.0,
                'lines' => $counts[$key],
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        return $rows;
    }

    private function classifyOutflow(string $description): string
    {
        $head = mb_strtoupper($this->counterpartyFromDescription($description));
        $full = mb_strtoupper($description.' '.$head);

        if (preg_match('/РЕСО.?ЛИЗИНГ|РЕСО.?АВТОЛИЗИНГ|СБЕРБАНК ЛИЗИНГ|ЛИЗИНГ/u', $full) === 1) {
            return 'leasing';
        }
        if (preg_match('/ОНЛАЙН.?КАРД|ТОПЛИВ|ГСМ/u', $full) === 1) {
            return 'fuel';
        }
        if (preg_match('/ФНС|НАЛОГ|ЕНП|УФК/u', $full) === 1) {
            return 'taxes';
        }
        if (preg_match('/ПЕРЕВОД МЕЖДУ СЧЕТАМИ|ГРОСС|АВТОАЛЬЯНС|ПРОФСФЕРА/u', $full) === 1) {
            return 'internal_transfers';
        }
        if (preg_match('/РТИТС|ПЛАТОН|ОСАГО|КАСКО|СТРАХ|ИНГОС|СОГАЗ|РЕСО.?ГАРАНТ/u', $full) === 1) {
            return 'insurance_platon';
        }
        if (preg_match('/РОСТЕЛЕКОМ|DNS|АТИ|ДНС РИТЕЙЛ|МТС|БИЛАЙН|АРЕНД|ОФИС/u', $full) === 1) {
            return 'office_it';
        }
        if (preg_match('/^СБЕРБАНК ПАО|^АЛЬФА.?БАНК|^БАНК ВТБ|КОМИСС/u', $head) === 1) {
            return 'bank';
        }
        if (preg_match('/АВЕТИСЯН|ЗЮЗИНА|ПИМАНОВА|КОЛЕСНИК|СБП|ЗАРПЛАТ|ФОТ/u', $full) === 1
            && preg_match('/ТРАНСПОРТ|ЭКСПЕДИЦ|ГРУЗ/u', $full) !== 1) {
            return 'payroll_individuals';
        }
        if (preg_match('/ТРАНСПОРТ|ЭКСПЕДИЦ|ГРУЗОПЕРЕВ|ЛОГИСТИК|КАРГО|ТРАНС|МОТОРС|ИМПЕРИЯ|ПИОНЕР|ПРАЙМ|МАН.?ГРУЗ|М\.Т\.Э\.К|ФОРТУНА|АВТОПАРТНЕР|СУЛЬДА|АРИС|АГЛ|ТРИО|СТАРМОБИЛ|ВОСТОЧНЫЙ|ВР ЛОГИСТИК|НЕДЗЕЛЬСКАЯ|ПАРФЕНОВА|ЧУРСИН|СОЮЗ/u', $full) === 1) {
            return 'carriers_logistics';
        }
        if (preg_match('/ИП /u', $head) === 1 && preg_match('/ТРАНСПОРТ|УСЛУГ/u', $full) === 1) {
            return 'carriers_logistics';
        }

        return 'other';
    }

    /**
     * @param  Collection<int, ManagementStatementLine>  $lines
     * @return array{in_count: int, in_amount: float, out_count: int, out_amount: float}
     */
    private function buildPending($lines): array
    {
        $inCount = 0;
        $inAmount = 0.0;
        $outCount = 0;
        $outAmount = 0.0;

        foreach ($lines as $line) {
            if ($line->status !== 'pending') {
                continue;
            }
            $amount = (float) $line->amount;
            if ($line->direction === 'in') {
                $inCount++;
                $inAmount += $amount;
            } else {
                $outCount++;
                $outAmount += $amount;
            }
        }

        return [
            'in_count' => $inCount,
            'in_amount' => round($inAmount, 2),
            'out_count' => $outCount,
            'out_amount' => round($outAmount, 2),
        ];
    }

    /**
     * @param  Collection<int, ManagementStatementLine>  $lines
     * @return array{total_amount: float, lines: int, top: list<array{name: string, amount: float, status: string}>}
     */
    private function buildOutflowsWithoutOrder($lines): array
    {
        $byName = [];
        $statusByName = [];
        $total = 0.0;
        $count = 0;

        foreach ($lines as $line) {
            if ($line->direction !== 'out' || $line->allocation_order_id !== null) {
                continue;
            }
            $name = $this->counterpartyFromDescription((string) $line->description);
            $amount = (float) $line->amount;
            $byName[$name] = ($byName[$name] ?? 0.0) + $amount;
            $statusByName[$name] = (string) $line->status;
            $total += $amount;
            $count++;
        }

        arsort($byName);
        $top = [];
        $i = 0;
        foreach ($byName as $name => $amount) {
            $top[] = [
                'name' => $name,
                'amount' => round($amount, 2),
                'status' => $statusByName[$name] ?? 'unknown',
            ];
            if (++$i >= self::OUTFLOW_WITHOUT_ORDER_LIMIT) {
                break;
            }
        }

        return [
            'total_amount' => round($total, 2),
            'lines' => $count,
            'top' => $top,
        ];
    }

    /**
     * @return array{total: float, clients_or_carriers: int, rows: list<array<string, mixed>>}
     */
    private function buildReceivablesPayables(CarbonImmutable $from, string $party): array
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasTable('orders')) {
            return ['total' => 0.0, 'clients_or_carriers' => 0, 'rows' => []];
        }

        $query = PaymentSchedule::query()
            ->join('orders', 'orders.id', '=', 'payment_schedules.order_id')
            ->where('payment_schedules.party', $party)
            ->where('payment_schedules.remaining_amount', '>', 0.009)
            ->whereDate('orders.created_at', '>=', $from->toDateString())
            ->select([
                'payment_schedules.id',
                'payment_schedules.order_id',
                'payment_schedules.remaining_amount',
                'payment_schedules.planned_date',
                'payment_schedules.counterparty_id',
                'orders.order_number',
            ]);

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function ($q): void {
                $q->whereNull('payment_schedules.is_partial')
                    ->orWhere('payment_schedules.is_partial', false);
            });
        }

        $schedules = $query->orderByDesc('payment_schedules.remaining_amount')->get();

        $byCounterparty = [];
        $rows = [];
        $total = 0.0;

        foreach ($schedules as $row) {
            $remaining = round((float) $row->remaining_amount, 2);
            $total += $remaining;
            $cpId = $row->counterparty_id !== null ? (int) $row->counterparty_id : 0;
            $byCounterparty[$cpId] = true;

            if (count($rows) < self::AR_AP_LIMIT) {
                $name = null;
                if ($cpId > 0 && Schema::hasTable('contractors')) {
                    $name = Contractor::query()->whereKey($cpId)->value('name');
                }
                $rows[] = [
                    'order_id' => (int) $row->order_id,
                    'order_number' => $row->order_number,
                    'counterparty' => $name ?: ($cpId > 0 ? '#'.$cpId : '—'),
                    'remaining' => $remaining,
                    'planned_date' => $row->planned_date,
                ];
            }
        }

        return [
            'total' => round($total, 2),
            'clients_or_carriers' => count($byCounterparty),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{in: float, out: float, net: float}  $totals
     * @param  array{in_count: int, in_amount: float, out_count: int, out_amount: float}  $pending
     * @param  array{total: float, clients_or_carriers: int, rows: list<array<string, mixed>>}  $ar
     * @param  array{total: float, clients_or_carriers: int, rows: list<array<string, mixed>>}  $ap
     */
    private function buildHeadline(array $totals, array $pending, array $ar, array $ap): string
    {
        $parts = [
            sprintf('Приход %.0f ₽', $totals['in']),
            sprintf('расход %.0f ₽', $totals['out']),
            sprintf('нетто %+.0f ₽', $totals['net']),
            sprintf('ДЗ %.0f ₽', $ar['total']),
            sprintf('КЗ %.0f ₽', $ap['total']),
        ];

        if (($pending['in_count'] + $pending['out_count']) > 0) {
            $parts[] = sprintf(
                'pending in %.0f / out %.0f ₽',
                $pending['in_amount'],
                $pending['out_amount'],
            );
        }

        return implode('; ', $parts).'.';
    }

    /**
     * @param  array{in: float, out: float, net: float}  $totals
     * @param  array{total: float, clients_or_carriers: int, rows: list<array<string, mixed>>}  $ar
     * @param  array{total: float, clients_or_carriers: int, rows: list<array<string, mixed>>}  $ap
     * @param  array{in_count: int, in_amount: float, out_count: int, out_amount: float}  $pending
     * @param  list<array{bucket: string, label: string, amount: float, share_pct: float}>  $structure
     * @return list<string>
     */
    private function buildDiagnosis(array $totals, array $ar, array $ap, array $pending, array $structure): array
    {
        $items = [];

        if ($totals['in'] > 0) {
            $retention = round(($totals['net'] / $totals['in']) * 100, 1);
            $items[] = sprintf(
                'Касса копит слабо: нетто %.1f%% от прихода (%.0f из %.0f ₽).',
                $retention,
                $totals['net'],
                $totals['in'],
            );
        }

        $fleetShare = 0.0;
        foreach ($structure as $row) {
            if (in_array($row['bucket'], ['leasing', 'fuel', 'insurance_platon'], true)) {
                $fleetShare += (float) $row['share_pct'];
            }
        }
        if ($fleetShare >= 15) {
            $items[] = sprintf('Собственный парк (лизинг+топливо+страховка) съедает ~%.0f%% исходящих.', $fleetShare);
        }

        if ($ar['total'] < $ap['total']) {
            $items[] = sprintf(
                'По графикам CRM кредиторка (%.0f) больше дебиторки (%.0f) — кассовый разрыв не из «нам все должны».',
                $ap['total'],
                $ar['total'],
            );
        } elseif ($ar['total'] > 0 && $ar['total'] < ($totals['in'] * 0.1)) {
            $items[] = 'Внешняя дебиторка невелика относительно оборота — главный рычаг не в сборе ДЗ.';
        }

        if (($pending['out_amount'] + $pending['in_amount']) > 0) {
            $items[] = sprintf(
                'Учёт отстаёт от банка: неразнесено входящих %.0f ₽ и исходящих %.0f ₽.',
                $pending['in_amount'],
                $pending['out_amount'],
            );
        }

        if ($items === []) {
            $items[] = 'Критических перекосов по доступным данным не видно — смотрите помесячный нетто и структуру исходящих.';
        }

        return $items;
    }

    /**
     * @param  array{in: float, out: float, net: float}  $totals
     * @param  array{in_count: int, in_amount: float, out_count: int, out_amount: float}  $pending
     * @param  array{total_amount: float, lines: int, top: list<array{name: string, amount: float}>}  $withoutOrder
     * @param  array{total: float, clients_or_carriers: int, rows: list<array<string, mixed>>}  $ar
     * @param  array{total: float, clients_or_carriers: int, rows: list<array<string, mixed>>}  $ap
     * @param  list<array{month: string, net: float}>  $byMonth
     * @return list<string>
     */
    private function buildActions(
        array $totals,
        array $pending,
        array $withoutOrder,
        array $ar,
        array $ap,
        array $byMonth,
    ): array {
        $actions = [];

        if (($pending['in_count'] + $pending['out_count']) > 0) {
            $actions[] = sprintf(
                'Доразнести pending: %d входящих (%.0f ₽) и %d исходящих (%.0f ₽) — иначе картина УУ врёт.',
                $pending['in_count'],
                $pending['in_amount'],
                $pending['out_count'],
                $pending['out_amount'],
            );
        }

        if ($withoutOrder['total_amount'] > 0 && $withoutOrder['top'] !== []) {
            $names = array_slice(array_column($withoutOrder['top'], 'name'), 0, 5);
            $actions[] = sprintf(
                'Разобрать исходящие без заказа в CRM (%.0f ₽): %s.',
                $withoutOrder['total_amount'],
                implode(', ', $names),
            );
        }

        $negativeMonths = array_values(array_filter(
            $byMonth,
            fn (array $row): bool => ($row['net'] ?? 0) < 0,
        ));
        if ($negativeMonths !== []) {
            $labels = array_map(fn (array $row): string => (string) $row['month'], $negativeMonths);
            $actions[] = 'Кассово отрицательные месяцы: '.implode(', ', $labels).' — сверить темп выплат перевозчикам и парку с приходами.';
        }

        if ($ar['total'] > 0 && $ar['rows'] !== []) {
            $top = $ar['rows'][0];
            $actions[] = sprintf(
                'Точечно закрыть ДЗ: %s / %s — %.0f ₽.',
                $top['order_number'] ?? 'заказ',
                $top['counterparty'] ?? 'клиент',
                (float) ($top['remaining'] ?? 0),
            );
        }

        if ($ap['total'] > $ar['total'] && $ap['total'] > 0) {
            $actions[] = sprintf(
                'Спланировать выплаты КЗ %.0f ₽ без просадки кассы (сейчас ДЗ только %.0f ₽).',
                $ap['total'],
                $ar['total'],
            );
        }

        if ($totals['net'] > 0 && $totals['in'] > 0 && ($totals['net'] / $totals['in']) < 0.08) {
            $actions[] = 'Вынести отдельно P&L «экспедиция vs собственный парк» — иначе свободные деньги всегда будут казаться меньше маржи заявок.';
        }

        if ($actions === []) {
            $actions[] = 'Критичных действий нет — повторяйте бриф помесячно и следите за pending выписки.';
        }

        return array_values(array_unique(array_slice($actions, 0, 8)));
    }

    private function counterpartyFromDescription(string $description): string
    {
        $description = trim($description);
        if ($description === '') {
            return '(пусто)';
        }

        $parts = preg_split('/\s*\/\s*/u', $description, 2);
        $name = trim((string) ($parts[0] ?? ''));

        if ($name === '') {
            return '(пусто)';
        }

        return mb_substr($name, 0, 120);
    }
}

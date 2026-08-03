<?php

namespace App\Services\Improvement;

use App\Models\ImprovementSignal;
use App\Models\Lead;
use App\Services\Commercial\ManagerDealSignalExtractor;
use App\Support\LeadCloseOutcomeFlagCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ImprovementSignalCollector
{
    public function __construct(
        private readonly ManagerDealSignalExtractor $signalExtractor,
    ) {}

    /**
     * @param  list<string>|null  $domains  null = все
     * @return list<ImprovementSignal>
     */
    public function collect(int $days = 30, ?array $domains = null): array
    {
        if (! Schema::hasTable('improvement_signals')) {
            return [];
        }

        $days = max(7, min(180, $days));
        $since = CarbonImmutable::now()->startOfDay()->subDays($days);
        $until = CarbonImmutable::now()->startOfDay();
        $wanted = $domains === null
            ? ['sales', 'documents', 'fleet', 'finance']
            : array_values(array_intersect($domains, ['sales', 'documents', 'fleet', 'finance']));

        $created = [];

        if (in_array('sales', $wanted, true)) {
            $created = array_merge($created, $this->collectSales($since, $until, $days));
        }
        if (in_array('documents', $wanted, true)) {
            $created = array_merge($created, $this->collectDocuments($since, $until, $days));
        }
        if (in_array('fleet', $wanted, true)) {
            $created = array_merge($created, $this->collectFleet($since, $until, $days));
        }
        if (in_array('finance', $wanted, true)) {
            $created = array_merge($created, $this->collectFinance($since, $until, $days));
        }

        return array_values(array_filter($created));
    }

    /**
     * @return list<ImprovementSignal>
     */
    private function collectSales(CarbonImmutable $since, CarbonImmutable $until, int $days): array
    {
        if (! Schema::hasTable('leads')) {
            return [];
        }

        $leads = Lead::query()
            ->whereIn('status', ['won', 'lost'])
            ->where('updated_at', '>=', $since)
            ->get();

        $lost = $leads->where('status', 'lost');
        $won = $leads->where('status', 'won');
        $totalClosed = $leads->count();
        $lostCount = $lost->count();
        $created = [];

        if ($totalClosed === 0) {
            return $created;
        }

        $lossFlagCounts = [];
        foreach ($lost as $lead) {
            $flag = $lead->close_outcome_primary_flag;
            $key = ($flag === null || $flag === '') ? 'unset' : (string) $flag;
            $lossFlagCounts[$key] = ($lossFlagCounts[$key] ?? 0) + 1;
        }
        arsort($lossFlagCounts);

        $hygieneGapCounts = [];
        $idleQualLost = 0;
        foreach ($lost as $lead) {
            $signal = $this->signalExtractor->extract($lead);
            foreach ($signal['hygiene_gaps'] ?? [] as $gap) {
                $hygieneGapCounts[$gap] = ($hygieneGapCounts[$gap] ?? 0) + 1;
            }
            if (! empty($signal['has_idle_qualification_dwell'])) {
                $idleQualLost++;
            }
        }
        arsort($hygieneGapCounts);

        $winRate = $totalClosed > 0 ? round($won->count() / $totalClosed * 100, 1) : 0.0;

        if ($winRate < 25.0 && $totalClosed >= 8) {
            $created[] = $this->upsertSignal('sales', 'win_rate_low', 'critical', "Низкий win rate за {$days} дн.: {$winRate}%", [
                'win_rate_pct' => $winRate,
                'closed' => $totalClosed,
                'won' => $won->count(),
                'lost' => $lostCount,
            ], $since, $until);
        }

        $topFlag = array_key_first($lossFlagCounts);
        if ($topFlag !== null && $topFlag !== 'unset' && ($lossFlagCounts[$topFlag] ?? 0) >= 2) {
            $label = LeadCloseOutcomeFlagCatalog::label($topFlag) ?? $topFlag;
            $count = $lossFlagCounts[$topFlag];
            $created[] = $this->upsertSignal(
                'sales',
                'loss_flag_spike',
                $count >= max(3, (int) ceil($lostCount * 0.4)) ? 'warn' : 'info',
                "Частая причина отказа: «{$label}» ({$count})",
                [
                    'flag' => $topFlag,
                    'label' => $label,
                    'count' => $count,
                    'lost_total' => $lostCount,
                    'loss_flag_counts' => $lossFlagCounts,
                ],
                $since,
                $until,
            );
        }

        if ($lostCount > 0 && ($lossFlagCounts['unset'] ?? 0) >= max(2, (int) ceil($lostCount * 0.3))) {
            $unset = $lossFlagCounts['unset'];
            $created[] = $this->upsertSignal('sales', 'close_outcome_missing', 'warn', "У {$unset} из {$lostCount} проигрышей нет флага причины", [
                'unset_count' => $unset,
                'lost_total' => $lostCount,
            ], $since, $until);
        }

        $noAuthority = $hygieneGapCounts['no_authority'] ?? 0;
        if ($lostCount > 0 && $noAuthority >= max(2, (int) ceil($lostCount * 0.4))) {
            $created[] = $this->upsertSignal('sales', 'hygiene_no_authority', 'warn', "В {$noAuthority} проигрышах не указан ЛПР", [
                'count' => $noAuthority,
                'lost_total' => $lostCount,
                'hygiene_gap_counts' => $hygieneGapCounts,
            ], $since, $until);
        }

        if ($idleQualLost >= 2 && $lostCount > 0 && $idleQualLost >= ($lostCount * 0.3)) {
            $created[] = $this->upsertSignal('sales', 'idle_qualification', 'warn', "Простой на квалификации: {$idleQualLost} из {$lostCount} проигрышей", [
                'idle_count' => $idleQualLost,
                'lost_total' => $lostCount,
            ], $since, $until);
        }

        $noProposal = $hygieneGapCounts['no_proposal_sent'] ?? 0;
        if ($noProposal >= 3) {
            $created[] = $this->upsertSignal('sales', 'no_proposal_sent', 'info', "Проигрыши без отправленного КП: {$noProposal}", [
                'count' => $noProposal,
                'lost_total' => $lostCount,
            ], $since, $until);
        }

        return $created;
    }

    /**
     * @return list<ImprovementSignal>
     */
    private function collectDocuments(CarbonImmutable $since, CarbonImmutable $until, int $days): array
    {
        if (! Schema::hasTable('payment_schedules')) {
            return [];
        }

        $today = $until->toDateString();
        $overdue = (int) DB::table('payment_schedules')
            ->whereIn('status', ['pending', 'overdue'])
            ->whereNotNull('planned_date')
            ->whereDate('planned_date', '<', $today)
            ->where(function ($q): void {
                $q->whereNull('remaining_amount')->orWhere('remaining_amount', '>', 0);
            })
            ->count();

        if ($overdue < 5) {
            return [];
        }

        $amount = (float) DB::table('payment_schedules')
            ->whereIn('status', ['pending', 'overdue'])
            ->whereNotNull('planned_date')
            ->whereDate('planned_date', '<', $today)
            ->sum(DB::raw('COALESCE(remaining_amount, amount, 0)'));

        return [
            $this->upsertSignal(
                'documents',
                'payment_schedules_overdue',
                $overdue >= 20 ? 'critical' : 'warn',
                "Просроченных строк графика оплаты: {$overdue} (~".number_format($amount, 0, '.', ' ').' ₽)',
                [
                    'overdue_count' => $overdue,
                    'overdue_amount' => round($amount, 2),
                    'days' => $days,
                ],
                $since,
                $until,
            ),
        ];
    }

    /**
     * @return list<ImprovementSignal>
     */
    private function collectFleet(CarbonImmutable $since, CarbonImmutable $until, int $days): array
    {
        if (! Schema::hasTable('fleet_trips')) {
            return [];
        }

        $staleDays = 7;
        $staleCutoff = CarbonImmutable::now()->subDays($staleDays);
        $stalePlanned = (int) DB::table('fleet_trips')
            ->where('status', 'planned')
            ->where('created_at', '<', $staleCutoff)
            ->count();

        if ($stalePlanned < 3) {
            return [];
        }

        return [
            $this->upsertSignal(
                'fleet',
                'fleet_trips_stale_planned',
                $stalePlanned >= 10 ? 'warn' : 'info',
                "Рейсов в статусе «planned» старше {$staleDays} дн.: {$stalePlanned}",
                [
                    'stale_planned_count' => $stalePlanned,
                    'stale_days' => $staleDays,
                ],
                $since,
                $until,
            ),
        ];
    }

    /**
     * @return list<ImprovementSignal>
     */
    private function collectFinance(CarbonImmutable $since, CarbonImmutable $until, int $days): array
    {
        if (! Schema::hasTable('management_statement_lines')) {
            return [];
        }

        $pending = (int) DB::table('management_statement_lines')
            ->where('status', 'pending')
            ->where('operation_date', '>=', $since->toDateString())
            ->count();

        if ($pending < 5) {
            return [];
        }

        $amount = (float) DB::table('management_statement_lines')
            ->where('status', 'pending')
            ->where('operation_date', '>=', $since->toDateString())
            ->sum('amount');

        return [
            $this->upsertSignal(
                'finance',
                'mgmt_unallocated_lines',
                $pending >= 20 ? 'critical' : 'warn',
                "Неразнесённых строк УУ за {$days} дн.: {$pending} (~".number_format($amount, 0, '.', ' ').' ₽)',
                [
                    'pending_lines' => $pending,
                    'pending_amount' => round($amount, 2),
                ],
                $since,
                $until,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertSignal(
        string $domain,
        string $kind,
        string $severity,
        string $title,
        array $payload,
        CarbonImmutable $since,
        CarbonImmutable $until,
    ): ImprovementSignal {
        $existing = ImprovementSignal::query()
            ->where('domain', $domain)
            ->where('kind', $kind)
            ->where('status', ImprovementSignal::STATUS_OPEN)
            ->whereDate('period_to', $until->toDateString())
            ->first();

        $attributes = [
            'domain' => $domain,
            'kind' => $kind,
            'severity' => $severity,
            'title' => $title,
            'payload' => $payload,
            'period_from' => $since->toDateString(),
            'period_to' => $until->toDateString(),
            'source' => 'rules',
            'status' => ImprovementSignal::STATUS_OPEN,
        ];

        if ($existing !== null) {
            $existing->fill($attributes);
            $existing->save();

            return $existing;
        }

        return ImprovementSignal::query()->create($attributes);
    }
}

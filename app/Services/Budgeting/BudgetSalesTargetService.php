<?php

namespace App\Services\Budgeting;

use App\Models\BudgetSalesTarget;
use App\Models\BudgetScenario;
use App\Models\User;
use App\Support\RoleAccess;
use Carbon\CarbonImmutable;

class BudgetSalesTargetService
{
    public function __construct(
        private readonly BudgetSalesScenarioService $salesScenarioService,
        private readonly BudgetSalesPerformanceService $performanceService,
    ) {}

    /**
     * @return array{
     *     scenario_id: int,
     *     period_month: string,
     *     period_options: list<array{value: string, label: string}>,
     *     metrics: list<array{key: string, label: string, unit: string}>,
     *     sellers: list<array{
     *         id: int,
     *         name: string,
     *         metrics: array<string, array{planned: float, actual: float, variance: float}>
     *     }>
     * }
     */
    public function buildPayload(BudgetScenario $companyScenario, ?string $periodMonthInput, int $horizonMonths): array
    {
        $salesScenario = $this->salesScenarioService->ensureForCompanyScenario($companyScenario);
        $periodMonth = $this->resolvePeriodMonth($periodMonthInput);
        $periodOptions = $this->periodOptions($horizonMonths);
        $sellers = $this->resolveSellers();
        $userIds = array_map(fn (array $seller): int => $seller['id'], $sellers);

        $plannedByUser = $this->plannedByUser($salesScenario, $periodMonth, $userIds);
        $actualByUser = $this->performanceService->actualsForMonth($periodMonth, $userIds);

        $metricsCatalog = BudgetSalesTarget::metricCatalog();
        $metrics = [];

        foreach (BudgetSalesTarget::metrics() as $metricKey) {
            $metrics[] = [
                'key' => $metricKey,
                'label' => $metricsCatalog[$metricKey]['label'],
                'unit' => $metricsCatalog[$metricKey]['unit'],
            ];
        }

        $sellerRows = [];

        foreach ($sellers as $seller) {
            $userId = $seller['id'];
            $metricRows = [];

            foreach (BudgetSalesTarget::metrics() as $metricKey) {
                $planned = (float) ($plannedByUser[$userId][$metricKey] ?? 0.0);
                $actual = (float) ($actualByUser[$userId][$metricKey] ?? 0.0);

                $metricRows[$metricKey] = [
                    'planned' => $planned,
                    'actual' => $actual,
                    'variance' => round($actual - $planned, 2),
                ];
            }

            $sellerRows[] = [
                'id' => $userId,
                'name' => $seller['name'],
                'metrics' => $metricRows,
            ];
        }

        return [
            'scenario_id' => $salesScenario->id,
            'period_month' => $periodMonth->toDateString(),
            'period_options' => $periodOptions,
            'metrics' => $metrics,
            'sellers' => $sellerRows,
        ];
    }

    /**
     * @param  list<array{user_id: int, metric: string, planned_value: float|int|string|null}>  $targets
     */
    public function upsert(BudgetScenario $companyScenario, CarbonImmutable $periodMonth, array $targets): void
    {
        $salesScenario = $this->salesScenarioService->ensureForCompanyScenario($companyScenario);
        $month = $periodMonth->startOfMonth()->toDateString();

        foreach ($targets as $row) {
            $metric = (string) ($row['metric'] ?? '');
            $userId = (int) ($row['user_id'] ?? 0);

            if ($userId <= 0 || ! in_array($metric, BudgetSalesTarget::metrics(), true)) {
                continue;
            }

            $plannedValue = max(0, (float) ($row['planned_value'] ?? 0));

            BudgetSalesTarget::query()->updateOrCreate(
                [
                    'scenario_id' => $salesScenario->id,
                    'user_id' => $userId,
                    'period_month' => $month,
                    'metric' => $metric,
                ],
                [
                    'planned_value' => $plannedValue,
                ],
            );
        }
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function periodOptions(int $horizonMonths): array
    {
        $horizonMonths = max(6, min(36, $horizonMonths));
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(2);
        $options = [];

        for ($index = 0; $index < $horizonMonths + 2; $index++) {
            $month = $start->addMonths($index);
            $options[] = [
                'value' => $month->format('Y-m-01'),
                'label' => $month->locale('ru')->translatedFormat('F Y'),
            ];
        }

        return $options;
    }

    private function resolvePeriodMonth(?string $periodMonthInput): CarbonImmutable
    {
        if ($periodMonthInput !== null && $periodMonthInput !== '') {
            try {
                return CarbonImmutable::parse($periodMonthInput)->startOfMonth();
            } catch (\Throwable) {
                // fall through
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function resolveSellers(): array
    {
        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(function (User $user): bool {
                $areas = RoleAccess::userVisibilityAreas($user);

                return RoleAccess::hasVisibilityArea($areas, 'leads')
                    || RoleAccess::hasVisibilityArea($areas, 'orders');
            })
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array<string, float>>
     */
    private function plannedByUser(BudgetScenario $salesScenario, CarbonImmutable $periodMonth, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = BudgetSalesTarget::query()
            ->where('scenario_id', $salesScenario->id)
            ->whereDate('period_month', $periodMonth->toDateString())
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'metric', 'planned_value']);

        $planned = [];

        foreach ($userIds as $userId) {
            $planned[$userId] = [];
        }

        foreach ($rows as $row) {
            $planned[(int) $row->user_id][(string) $row->metric] = (float) $row->planned_value;
        }

        return $planned;
    }
}

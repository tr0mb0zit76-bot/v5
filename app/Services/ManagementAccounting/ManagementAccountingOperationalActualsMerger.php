<?php

namespace App\Services\ManagementAccounting;

use App\Models\FleetTrip;
use App\Models\ManagementExpenseCategory;
use App\Models\PaymentSchedulePaymentEvent;
use App\Support\ManagementAccountingPeriodSupport;
use App\Support\ManagementCostCategoryCodes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

class ManagementAccountingOperationalActualsMerger
{
    public function __construct(
        private readonly ManagementOperationalCostCategoryResolver $costResolver,
    ) {}

    /**
     * @param  array{in: float, out: float}  $totals
     * @param  array<int, array{in: float, out: float}>  $byCategory
     */
    public function mergePaymentEvents(
        CarbonImmutable $start,
        CarbonImmutable $end,
        array &$totals,
        array &$byCategory,
    ): void {
        if (! Schema::hasTable('payment_schedule_payment_events')) {
            return;
        }

        $customerCategoryId = $this->categoryIdByCode('operational_customer_in');

        PaymentSchedulePaymentEvent::query()
            ->active()
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($query): void {
                $query->whereNull('transaction_reference')
                    ->orWhere('transaction_reference', 'not like', 'mgmt:%');
            })
            ->get(['party', 'amount', 'order_id', 'contractor_id'])
            ->each(function (PaymentSchedulePaymentEvent $event) use (&$totals, &$byCategory, $customerCategoryId): void {
                $amount = (float) $event->amount;
                if ($amount <= 0) {
                    return;
                }

                $party = strtolower(trim((string) $event->party));

                if ($party === 'customer' && $customerCategoryId !== null) {
                    $this->addBucket($totals, $byCategory, 'in', $amount, $customerCategoryId);

                    return;
                }

                if (in_array($party, ['carrier', 'contractor'], true)) {
                    $categoryId = $this->costResolver->categoryIdForCarrier(
                        $event->order_id !== null ? (int) $event->order_id : null,
                        $event->contractor_id !== null ? (int) $event->contractor_id : null,
                    );

                    if ($categoryId !== null) {
                        $this->addBucket($totals, $byCategory, 'out', $amount, $categoryId);
                    }
                }
            });
    }

    /**
     * @param  list<array{key: string, start: string, end: string}>  $columns
     * @param  array<int, array<string, array{in: float, out: float}>>  $byCategory
     */
    public function mergePaymentEventsByColumn(
        array $columns,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array &$byCategory,
    ): void {
        if (! Schema::hasTable('payment_schedule_payment_events')) {
            return;
        }

        $customerCategoryId = $this->categoryIdByCode('operational_customer_in');

        PaymentSchedulePaymentEvent::query()
            ->active()
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($query): void {
                $query->whereNull('transaction_reference')
                    ->orWhere('transaction_reference', 'not like', 'mgmt:%');
            })
            ->get(['party', 'amount', 'payment_date', 'order_id', 'contractor_id'])
            ->each(function (PaymentSchedulePaymentEvent $event) use (&$byCategory, $columns, $customerCategoryId): void {
                $amount = (float) $event->amount;
                if ($amount <= 0) {
                    return;
                }

                $columnKey = $this->columnKeyForDate($columns, (string) $event->payment_date);
                if ($columnKey === null) {
                    return;
                }

                $party = strtolower(trim((string) $event->party));

                if ($party === 'customer' && $customerCategoryId !== null) {
                    $this->addColumnBucket($byCategory, $customerCategoryId, $columnKey, 'in', $amount);

                    return;
                }

                if (in_array($party, ['carrier', 'contractor'], true)) {
                    $categoryId = $this->costResolver->categoryIdForCarrier(
                        $event->order_id !== null ? (int) $event->order_id : null,
                        $event->contractor_id !== null ? (int) $event->contractor_id : null,
                    );

                    if ($categoryId !== null) {
                        $this->addColumnBucket($byCategory, $categoryId, $columnKey, 'out', $amount);
                    }
                }
            });
    }

    /**
     * @param  array{in: float, out: float}  $totals
     * @param  array<int, array{in: float, out: float}>  $byCategory
     */
    public function mergeFleetTrips(
        CarbonImmutable $start,
        CarbonImmutable $end,
        array &$totals,
        array &$byCategory,
    ): void {
        if (! Schema::hasTable('fleet_trips')) {
            return;
        }

        $ownFleetCategoryId = $this->categoryIdByCode(ManagementCostCategoryCodes::OWN_FLEET);
        if ($ownFleetCategoryId === null) {
            return;
        }

        FleetTrip::query()
            ->where('status', 'completed')
            ->where('total_cost', '>', 0)
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('completed_at', [$start->startOfDay(), $end->endOfDay()])
                    ->orWhere(function ($fallback) use ($start, $end): void {
                        $fallback->whereNull('completed_at')
                            ->whereBetween('started_at', [$start->startOfDay(), $end->endOfDay()]);
                    });
            })
            ->get(['total_cost'])
            ->each(function (FleetTrip $trip) use (&$totals, &$byCategory, $ownFleetCategoryId): void {
                $amount = (float) $trip->total_cost;
                if ($amount <= 0) {
                    return;
                }

                $this->addBucket($totals, $byCategory, 'out', $amount, $ownFleetCategoryId);
            });
    }

    /**
     * @param  list<array{key: string, start: string, end: string}>  $columns
     * @param  array<int, array<string, array{in: float, out: float}>>  $byCategory
     */
    public function mergeFleetTripsByColumn(
        array $columns,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array &$byCategory,
    ): void {
        if (! Schema::hasTable('fleet_trips')) {
            return;
        }

        $ownFleetCategoryId = $this->categoryIdByCode(ManagementCostCategoryCodes::OWN_FLEET);
        if ($ownFleetCategoryId === null) {
            return;
        }

        FleetTrip::query()
            ->where('status', 'completed')
            ->where('total_cost', '>', 0)
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('completed_at', [$start->startOfDay(), $end->endOfDay()])
                    ->orWhere(function ($fallback) use ($start, $end): void {
                        $fallback->whereNull('completed_at')
                            ->whereBetween('started_at', [$start->startOfDay(), $end->endOfDay()]);
                    });
            })
            ->get(['total_cost', 'completed_at', 'started_at'])
            ->each(function (FleetTrip $trip) use (&$byCategory, $columns, $ownFleetCategoryId): void {
                $amount = (float) $trip->total_cost;
                if ($amount <= 0) {
                    return;
                }

                $date = $trip->completed_at?->toDateString() ?? $trip->started_at?->toDateString();
                if ($date === null) {
                    return;
                }

                $columnKey = $this->columnKeyForDate($columns, $date);
                if ($columnKey === null) {
                    return;
                }

                $this->addColumnBucket($byCategory, $ownFleetCategoryId, $columnKey, 'out', $amount);
            });
    }

    /**
     * @param  array{in: float, out: float}  $totals
     * @param  array<int, array{in: float, out: float}>  $byCategory
     */
    private function addBucket(
        array &$totals,
        array &$byCategory,
        string $direction,
        float $amount,
        int $categoryId,
    ): void {
        if ($direction === 'in') {
            $totals['in'] += $amount;
        } elseif ($direction === 'out') {
            $totals['out'] += $amount;
        }

        if (! isset($byCategory[$categoryId])) {
            $byCategory[$categoryId] = ['in' => 0.0, 'out' => 0.0];
        }

        if ($direction === 'in') {
            $byCategory[$categoryId]['in'] += $amount;
        } elseif ($direction === 'out') {
            $byCategory[$categoryId]['out'] += $amount;
        }
    }

    /**
     * @param  array<int, array<string, array{in: float, out: float}>>  $byCategory
     */
    private function addColumnBucket(
        array &$byCategory,
        int $categoryId,
        string $columnKey,
        string $direction,
        float $amount,
    ): void {
        $byCategory[$categoryId][$columnKey] ??= ['in' => 0.0, 'out' => 0.0];

        if ($direction === 'in') {
            $byCategory[$categoryId][$columnKey]['in'] += $amount;
        } elseif ($direction === 'out') {
            $byCategory[$categoryId][$columnKey]['out'] += $amount;
        }
    }

    /**
     * @param  list<array{key: string, start: string, end: string}>  $columns
     */
    private function columnKeyForDate(array $columns, string $date): ?string
    {
        return ManagementAccountingPeriodSupport::columnKeyForDate($columns, $date);
    }

    private function categoryIdByCode(string $code): ?int
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return null;
        }

        $id = ManagementExpenseCategory::query()->where('code', $code)->value('id');

        return $id !== null ? (int) $id : null;
    }
}

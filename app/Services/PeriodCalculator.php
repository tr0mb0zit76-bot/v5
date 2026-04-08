<?php

namespace App\Services;

use App\Models\Order;
use App\Support\CarrierPaymentFormResolver;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class PeriodCalculator
{
    public function __construct(
        private readonly DealTypeClassifier $dealTypeClassifier,
    ) {}

    /**
     * @return array{start: string, end: string, name: string}
     */
    public function getPeriodForDate(string $date): array
    {
        $periodDate = Carbon::parse($date);

        if ($periodDate->day <= 15) {
            return [
                'start' => $periodDate->copy()->startOfMonth()->toDateString(),
                'end' => $periodDate->copy()->day(15)->toDateString(),
                'name' => 'first_half',
            ];
        }

        return [
            'start' => $periodDate->copy()->day(16)->toDateString(),
            'end' => $periodDate->copy()->endOfMonth()->toDateString(),
            'name' => 'second_half',
        ];
    }

    /**
     * @return array{total:int,direct:int,indirect:int,direct_ratio:float,indirect_ratio:float}
     */
    public function getManagerPeriodStats(int $managerId, string $periodStart, string $periodEnd): array
    {
        try {
            $query = Order::query()
                ->where('manager_id', $managerId)
                ->whereBetween('order_date', [$periodStart, $periodEnd])
                ->whereNotNull('customer_payment_form')
                ->when(
                    Schema::hasColumn('orders', 'deleted_at'),
                    fn ($query) => $query->whereNull('deleted_at')
                );

            if (Schema::hasColumn('orders', 'carrier_payment_form')) {
                $query->whereNotNull('carrier_payment_form');
            } else {
                $eager = [];
                if (Schema::hasTable('financial_terms')) {
                    $eager[] = 'financialTerms';
                }
                if (Schema::hasTable('leg_costs')) {
                    $eager[] = 'legs.cost';
                }
                if ($eager !== []) {
                    $query->with($eager);
                }
            }

            $orders = $query->get(
                Schema::hasColumn('orders', 'carrier_payment_form')
                    ? ['id', 'customer_payment_form', 'carrier_payment_form']
                    : ['id', 'customer_payment_form'],
            );
        } catch (QueryException) {
            return [
                'total' => 0,
                'direct' => 0,
                'indirect' => 0,
                'direct_ratio' => 0.0,
                'indirect_ratio' => 0.0,
            ];
        }

        if (! Schema::hasColumn('orders', 'carrier_payment_form')) {
            $orders = $orders->filter(function (Order $order): bool {
                return ! blank(CarrierPaymentFormResolver::forOrder($order));
            });
        }

        $total = $orders->count();
        $direct = $orders
            ->filter(fn (Order $order): bool => $this->dealTypeClassifier->classify($order) === 'direct')
            ->count();

        $indirect = $total - $direct;

        return [
            'total' => $total,
            'direct' => $direct,
            'indirect' => $indirect,
            'direct_ratio' => $total > 0 ? round($direct / $total, 2) : 0.0,
            'indirect_ratio' => $total > 0 ? round($indirect / $total, 2) : 0.0,
        ];
    }
}

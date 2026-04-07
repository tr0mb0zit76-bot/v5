<?php

namespace App\Services\KPI;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PeriodCalculator
{
    protected DealTypeClassifier $classifier;

    public function __construct()
    {
        $this->classifier = new DealTypeClassifier;
    }

    /**
     * Получение периода по дате
     */
    public function getPeriodForDate($date): array
    {
        $date = Carbon::parse($date);
        $day = $date->day;

        if ($day <= 15) {
            return [
                'start' => $date->copy()->startOfMonth()->format('Y-m-d'),
                'end' => $date->copy()->day(15)->format('Y-m-d'),
                'name' => 'первая половина '.$date->format('F Y'),
            ];
        } else {
            return [
                'start' => $date->copy()->day(16)->format('Y-m-d'),
                'end' => $date->copy()->endOfMonth()->format('Y-m-d'),
                'name' => 'вторая половина '.$date->format('F Y'),
            ];
        }
    }

    /**
     * Получение статистики периода с кэшированием
     */
    public function getManagerPeriodStats($managerId, $periodStart, $periodEnd): array
    {
        $cacheKey = "period_stats_{$managerId}_{$periodStart}_{$periodEnd}";

        // Пробуем получить из кэша
        $stats = Cache::get($cacheKey);

        if ($stats !== null) {
            Log::debug('Period stats retrieved from cache', [
                'manager_id' => $managerId,
                'period' => "{$periodStart} - {$periodEnd}",
            ]);

            return $stats;
        }

        // Если нет в кэше - вычисляем
        Log::debug('Period stats cache miss, calculating...', [
            'manager_id' => $managerId,
            'period' => "{$periodStart} - {$periodEnd}",
        ]);

        // Получаем только нужные поля для оптимизации
        $orders = Order::where('manager_id', $managerId)
            ->whereBetween('order_date', [$periodStart, $periodEnd])
            ->whereNotNull('customer_payment_form')
            ->whereNotNull('carrier_payment_form')
            ->get(['id', 'customer_payment_form', 'carrier_payment_form']);

        $total = $orders->count();
        $direct = 0;

        foreach ($orders as $order) {
            if ($order->customer_payment_form === $order->carrier_payment_form) {
                $direct++;
            }
        }

        $directRatio = $total > 0 ? round($direct / $total, 2) : 0;

        $stats = [
            'total' => $total,
            'direct' => $direct,
            'indirect' => $total - $direct,
            'direct_ratio' => $directRatio,
            'indirect_ratio' => $total > 0 ? round(($total - $direct) / $total, 2) : 0,
        ];

        // Сохраняем в кэш на 5 минут
        Cache::put($cacheKey, $stats, 300);

        Log::info('Period stats calculated and cached', [
            'manager_id' => $managerId,
            'period' => "{$periodStart} - {$periodEnd}",
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * Очистка кэша периода
     */
    public function clearPeriodCache($managerId, $periodStart, $periodEnd): void
    {
        $cacheKey = "period_stats_{$managerId}_{$periodStart}_{$periodEnd}";
        Cache::forget($cacheKey);

        Log::info('Period cache cleared', [
            'manager_id' => $managerId,
            'period' => "{$periodStart} - {$periodEnd}",
        ]);
    }

    /**
     * Очистка всех кэшей периода для менеджера
     */
    public function clearAllManagerCaches($managerId): void
    {
        // В Laravel нет прямого удаления по паттерну, поэтому используем теги
        // или просто полагаемся на TTL
        Log::info('All period caches for manager marked for invalidation', [
            'manager_id' => $managerId,
        ]);

        // Если используется кэш с поддержкой тегов (Redis, Memcached)
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(["period_stats_manager_{$managerId}"])->flush();
        }
    }
}

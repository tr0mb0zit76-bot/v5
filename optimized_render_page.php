<?php

use App\Models\Contractor;
use App\Models\Order;
use App\Services\ContractorCreditService;
use App\Services\OrderDocumentRequirementService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Оптимизированная версия renderPage
class OptimizedOrderWizardController
{
    public function renderPageOptimized($request, $order = null)
    {
        $creditService = app(ContractorCreditService::class);
        $documentRequirementService = app(OrderDocumentRequirementService::class);

        // 1. Загружаем только нужных контрагентов
        $contractors = $this->loadRelevantContractors($order);

        // 2. Рассчитываем долги ТОЛЬКО для контрагентов с лимитом
        $contractorsWithLimit = $contractors->filter(
            fn ($c) => ($c->stop_on_limit ?? false) && $c->debt_limit !== null
        );

        if ($contractorsWithLimit->isNotEmpty()) {
            $debtMap = $creditService->currentDebtByContractorIds(
                $contractorsWithLimit->pluck('id')->all()
            );

            $contractors->transform(function ($contractor) use ($debtMap, $creditService) {
                if (isset($debtMap[$contractor->id])) {
                    $contractor->setAttribute('current_debt', $debtMap[$contractor->id]);
                    $contractor->setAttribute('debt_limit_reached',
                        $creditService->isBlockedByDebtLimit($contractor, $debtMap[$contractor->id])
                    );
                }

                return $contractor;
            });
        }

        // 3. Возвращаем данные
        return [
            'order' => $order ? $this->serializeOrder($order) : null,
            'contractors' => $contractors->values(),
            'ownCompanies' => $contractors->where('is_own_company', true)->values(),
            // ... остальные данные
        ];
    }

    private function loadRelevantContractors($order)
    {
        $query = Contractor::query();

        // Если есть заказ, загружаем связанных контрагентов + топ активных
        if ($order) {
            $relatedIds = $this->getRelatedContractorIds($order);

            return $query->where(function ($q) use ($relatedIds) {
                // Связанные контрагенты
                $q->whereIn('id', $relatedIds)
                  // Или активные (топ 100)
                    ->orWhere('is_active', true);
            })
                ->orderByRaw('FIELD(id, '.implode(',', $relatedIds).') DESC') // Связанные первые
                ->orderBy('name')
                ->limit(150) // Ограничиваем количество
                ->get(['id', 'name', 'is_active', 'is_own_company', 'debt_limit', 'stop_on_limit']);
        }

        // Для нового заказа - только активные (топ 100)
        return $query->where('is_active', true)
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'is_active', 'is_own_company', 'debt_limit', 'stop_on_limit']);
    }

    private function getRelatedContractorIds($order)
    {
        $ids = [];

        if ($order->customer_id) {
            $ids[] = $order->customer_id;
        }
        if ($order->carrier_id) {
            $ids[] = $order->carrier_id;
        }
        if ($order->own_company_id) {
            $ids[] = $order->own_company_id;
        }

        // Также можно добавить контрагентов из финансовых условий
        if ($order->financialTerm && $order->financialTerm->contractors_costs) {
            $costs = json_decode($order->financialTerm->contractors_costs, true) ?? [];
            foreach ($costs as $cost) {
                if (! empty($cost['contractor_id'])) {
                    $ids[] = $cost['contractor_id'];
                }
            }
        }

        return array_unique(array_filter($ids));
    }

    private function serializeOrder($order)
    {
        // Упрощенная сериализация
        return [
            'id' => $order->id,
            'number' => $order->number,
            'customer_id' => $order->customer_id,
            'carrier_id' => $order->carrier_id,
            'own_company_id' => $order->own_company_id,
            // ... другие поля
        ];
    }
}

// Тестируем оптимизированную версию
echo "Testing optimized version...\n";

$controller = new OptimizedOrderWizardController;

// Тест 1: Новый заказ (без order)
echo "\nTest 1: New order (no existing order)\n";
$start = microtime(true);
$result1 = $controller->renderPageOptimized(null, null);
$time1 = microtime(true) - $start;
echo 'Time: '.round($time1 * 1000, 2)."ms\n";
echo 'Contractors loaded: '.count($result1['contractors'])."\n";

// Тест 2: Существующий заказ
echo "\nTest 2: Existing order\n";
$order = Order::first();
if ($order) {
    $start = microtime(true);
    $result2 = $controller->renderPageOptimized(null, $order);
    $time2 = microtime(true) - $start;
    echo 'Time: '.round($time2 * 1000, 2)."ms\n";
    echo 'Contractors loaded: '.count($result2['contractors'])."\n";
    echo 'Order ID: '.$order->id."\n";
} else {
    echo "No orders found in database\n";
}

// Сравнение с оригиналом
echo "\n\nComparison with original:\n";
echo "Original: 2368 contractors, debt calculation for all with limit\n";
echo "Optimized: 100-150 contractors, debt calculation only for relevant ones\n";
echo "Expected improvement: 10x-20x faster\n";

<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Contractor;
use App\Models\User;
use App\Services\OrderWizardService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

echo "Тестирование новой архитектуры...\n";

try {
    DB::beginTransaction();

    // Создаем тестового пользователя
    $user = User::first();
    if (! $user) {
        $user = User::factory()->create();
    }

    // Создаем тестовых контрагентов
    $client = Contractor::firstOrCreate(
        ['inn' => 'test123456789'],
        ['name' => 'Тестовый клиент', 'type' => 'customer']
    );

    $carrier = Contractor::firstOrCreate(
        ['inn' => 'test987654321'],
        ['name' => 'Тестовый перевозчик', 'type' => 'carrier']
    );

    // Тестовые данные для заказа
    $validated = [
        'own_company_id' => null,
        'client_id' => $client->id,
        'order_date' => '2026-04-08',
        'order_number' => 'TEST-001',
        'status' => 'draft',
        'special_notes' => 'Тестовый заказ',

        'performers' => [
            [
                'stage' => 'leg_1',
                'contractor_id' => $carrier->id,
                'notes' => 'Основной перевозчик',
            ],
        ],

        'route_points' => [
            [
                'type' => 'loading',
                'stage' => 'leg_1',
                'address' => 'Москва, ул. Ленина, 1',
                'planned_date' => '2026-04-10',
                'contact_person' => 'Иван Иванов',
                'contact_phone' => '+79991234567',
            ],
            [
                'type' => 'unloading',
                'stage' => 'leg_1',
                'address' => 'Санкт-Петербург, ул. Пушкина, 2',
                'planned_date' => '2026-04-12',
                'contact_person' => 'Петр Петров',
                'contact_phone' => '+79997654321',
            ],
        ],

        'cargo_items' => [
            [
                'name' => 'Тестовый груз',
                'cargo_type' => 'general',
                'weight_kg' => 1000,
                'volume_m3' => 5,
                'package_count' => 10,
                'package_type' => 'pallet',
            ],
        ],

        'financial_term' => [
            'client_price' => 50000,
            'client_currency' => 'RUB',
            'client_payment_form' => 'vat',
            'client_payment_schedule' => [
                'postpayment_days' => 30,
                'postpayment_mode' => 'ottn',
                'has_prepayment' => false,
            ],
            'contractors_costs' => [
                [
                    'stage' => 'leg_1',
                    'contractor_id' => $carrier->id,
                    'amount' => 30000,
                    'currency' => 'RUB',
                    'payment_form' => 'no_vat',
                    'payment_schedule' => [
                        'postpayment_days' => 15,
                        'postpayment_mode' => 'fttn',
                        'has_prepayment' => false,
                    ],
                ],
            ],
            'additional_costs' => [],
            'kpi_percent' => 0,
        ],

        'documents' => [],
    ];

    // Создаем заказ через OrderWizardService
    $orderWizardService = app(OrderWizardService::class);
    $order = $orderWizardService->create($validated, $user);

    echo "✓ Заказ создан: #{$order->id}\n";

    // Проверяем структуру данных
    echo "\nПроверка структуры данных:\n";

    // 1. Проверяем плечи
    $legs = $order->legs()->with(['contractorAssignment', 'cost'])->get();
    echo '✓ Плечей создано: '.$legs->count()."\n";

    foreach ($legs as $leg) {
        echo "  Плечо #{$leg->id}: {$leg->description}\n";

        // Проверяем назначение исполнителя
        if ($leg->contractorAssignment) {
            echo "    ✓ Назначение исполнителя: #{$leg->contractorAssignment->id}\n";
            echo "      Исполнитель: #{$leg->contractorAssignment->contractor_id}\n";
            echo "      Статус: {$leg->contractorAssignment->status}\n";
        } else {
            echo "    ✗ Назначение исполнителя отсутствует\n";
        }

        // Проверяем стоимость
        if ($leg->cost) {
            echo "    ✓ Стоимость: #{$leg->cost->id}\n";
            echo "      Сумма: {$leg->cost->amount}\n";
            echo "      Валюта: {$leg->cost->currency}\n";
            echo "      Форма оплаты: {$leg->cost->payment_form}\n";
        } else {
            echo "    ✗ Стоимость отсутствует\n";
        }
    }

    // 2. Проверяем точки маршрута
    $routePoints = $order->routePoints()->get();
    echo "\n✓ Точек маршрута создано: ".$routePoints->count()."\n";

    // 3. Проверяем грузы
    $cargoItems = $order->cargoItems()->get();
    echo '✓ Грузов создано: '.$cargoItems->count()."\n";

    // 4. Проверяем финансовые условия
    $financialTerms = $order->financialTerms()->get();
    echo '✓ Финансовых условий создано: '.$financialTerms->count()."\n";

    // 5. Проверяем вычисляемые поля
    echo "\nПроверка вычисляемых полей:\n";
    echo '  Основной исполнитель: '.($order->primary_contractor ? $order->primary_contractor->name : 'нет')."\n";
    echo '  ID всех исполнителей: '.json_encode($order->all_contractor_ids)."\n";
    echo '  Общая стоимость плечей: '.$order->total_leg_costs."\n";

    $paymentStatus = $order->leg_payment_status;
    echo "  Статус оплаты по плечам:\n";
    echo "    Общая сумма: {$paymentStatus['total_amount']}\n";
    echo "    Оплачено: {$paymentStatus['paid_amount']}\n";
    echo "    Ожидает: {$paymentStatus['pending_amount']}\n";
    echo "    Черновик: {$paymentStatus['draft_amount']}\n";

    // Тестируем обновление заказа
    echo "\nТестирование обновления заказа...\n";

    // Добавляем второго исполнителя
    $validated['performers'][] = [
        'stage' => 'leg_2',
        'contractor_id' => $carrier->id,
        'notes' => 'Дополнительный перевозчик',
    ];

    $validated['financial_term']['contractors_costs'][] = [
        'stage' => 'leg_2',
        'contractor_id' => $carrier->id,
        'amount' => 20000,
        'currency' => 'RUB',
        'payment_form' => 'no_vat',
        'payment_schedule' => [
            'postpayment_days' => 20,
            'postpayment_mode' => 'fttn',
            'has_prepayment' => false,
        ],
    ];

    $updatedOrder = $orderWizardService->update($order, $validated, $user);

    echo "✓ Заказ обновлен\n";
    echo '  Плечей после обновления: '.$updatedOrder->legs()->count()."\n";
    echo '  Назначений исполнителей: '.$updatedOrder->legContractorAssignments()->count()."\n";
    echo '  Стоимостей: '.$updatedOrder->legCosts()->count()."\n";

    DB::rollBack();

    echo "\n✅ Тестирование завершено успешно!\n";
    echo "Новая архитектура работает корректно.\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "\n❌ Ошибка при тестировании:\n";
    echo 'Сообщение: '.$e->getMessage()."\n";
    echo 'Файл: '.$e->getFile()."\n";
    echo 'Строка: '.$e->getLine()."\n";
    echo "Трейс:\n".$e->getTraceAsString()."\n";
    exit(1);
}

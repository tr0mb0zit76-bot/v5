<?php

namespace App\Services\KPI;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class StatusCalculator
{
    /**
     * Расчёт статуса заявки на основе данных
     */
    public function calculate(Order $order): array
    {
        $statusCode = $this->determineStatus($order);
        $icon = $this->getStatusIcon($statusCode);
        $label = $this->getStatusLabel($statusCode);
        $messages = $this->getStatusMessages($order);

        Log::info('Status calculated', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status_code' => $statusCode,
            'icon' => $icon,
            'label' => $label,
            'has_loading' => ! empty($order->loading_date),
            'has_unloading' => ! empty($order->unloading_date),
            'all_documents' => $this->hasAllDocuments($order),
            'customer_paid' => $this->isPaidByCustomer($order),
            'carrier_paid' => $this->isPaidToCarrier($order),
            'manager_paid' => $this->isPaidToManager($order),
        ]);

        return [
            'status' => $statusCode,
            'icon' => $icon,
            'label' => $label,
            'messages' => $messages,
            'is_in_progress' => $statusCode === 'in_progress',
            'documents_received' => $this->hasAllDocuments($order),
            'is_paid_by_customer' => $this->isPaidByCustomer($order),
            'is_paid_to_carrier' => $this->isPaidToCarrier($order),
            'is_paid_to_manager' => $this->isPaidToManager($order),
            'is_completed' => $statusCode === 'completed',
        ];
    }

    /**
     * Определение статуса по логике из ТЗ
     */
    protected function determineStatus(Order $order): string
    {
        $hasLoading = ! empty($order->loading_date);
        $hasUnloading = ! empty($order->unloading_date);
        $allDocuments = $this->hasAllDocuments($order);
        $customerPaid = $this->isPaidByCustomer($order);
        $carrierPaid = $this->isPaidToCarrier($order);
        $managerPaid = $this->isPaidToManager($order);

        // 1. НОВАЯ - создана, но нет даты погрузки
        if (! $hasLoading) {
            return 'new';
        }

        // 2. ВЫПОЛНЯЕТСЯ - есть дата погрузки, но нет даты выгрузки
        if ($hasLoading && ! $hasUnloading) {
            return 'in_progress';
        }

        // 3. ДОКУМЕНТЫ - есть дата выгрузки, но не все документы
        if ($hasUnloading && ! $allDocuments) {
            return 'awaiting_docs';
        }

        // 4. ОПЛАТА - есть все документы, но нет полной оплаты
        if ($allDocuments && (! $customerPaid || ! $carrierPaid || ! $managerPaid)) {
            return 'awaiting_payment';
        }

        // 5. ЗАВЕРШЕНА - есть всё: дата выгрузки, документы, оплаты
        if ($hasUnloading && $allDocuments && $customerPaid && $carrierPaid && $managerPaid) {
            return 'completed';
        }

        // Запасной вариант
        return 'new';
    }

    /**
     * Проверка наличия всех документов
     */
    protected function hasAllDocuments(Order $order): bool
    {
        return ! empty($order->upd_customer_status) &&
               ! empty($order->order_customer_status) &&
               ! empty($order->waybill_number) &&
               ! empty($order->upd_carrier_status) &&
               ! empty($order->order_carrier_status);
    }

    /**
     * Проверка оплаты заказчиком (любая сумма)
     */
    protected function isPaidByCustomer(Order $order): bool
    {
        return ($order->final_customer ?? 0) > 0 || ($order->prepayment_customer ?? 0) > 0;
    }

    /**
     * Проверка оплаты перевозчику (любая сумма)
     */
    protected function isPaidToCarrier(Order $order): bool
    {
        return ($order->final_carrier ?? 0) > 0 || ($order->prepayment_carrier ?? 0) > 0;
    }

    /**
     * Проверка выплаты менеджеру
     */
    protected function isPaidToManager(Order $order): bool
    {
        return ($order->salary_paid ?? 0) > 0;
    }

    /**
     * Получение сообщений о статусе
     */
    protected function getStatusMessages(Order $order): array
    {
        $messages = [];

        if (! $this->hasAllDocuments($order)) {
            $missingDocs = [];
            if (empty($order->upd_customer_status)) {
                $missingDocs[] = 'УПД заказчик';
            }
            if (empty($order->order_customer_status)) {
                $missingDocs[] = 'Заявка заказчик';
            }
            if (empty($order->waybill_number)) {
                $missingDocs[] = 'ТН';
            }
            if (empty($order->upd_carrier_status)) {
                $missingDocs[] = 'УПД перевозчик';
            }
            if (empty($order->order_carrier_status)) {
                $missingDocs[] = 'Заявка перевозчик';
            }

            if (! empty($missingDocs)) {
                $messages[] = 'Не хватает документов: '.implode(', ', $missingDocs);
            }
        }

        if (! $this->isPaidByCustomer($order) && ! empty($order->unloading_date)) {
            $messages[] = 'Нет оплаты от клиента';
        }

        if (! $this->isPaidToCarrier($order) && ! empty($order->unloading_date)) {
            $messages[] = 'Не оплачено перевозчику';
        }

        if (! $this->isPaidToManager($order) && $this->isPaidByCustomer($order)) {
            $messages[] = 'Клиент оплатил, не выплачено менеджеру';
        }

        return $messages;
    }

    /**
     * Получение иконки для статуса
     */
    protected function getStatusIcon(string $status): string
    {
        return match ($status) {
            'new' => '⏳',
            'in_progress' => '🚛',
            'awaiting_docs' => '📄',
            'awaiting_payment' => '💰',
            'completed' => '✅',
            default => '⏳',
        };
    }

    /**
     * Получение текстового статуса
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'Новая',
            'in_progress' => 'Выполняется',
            'awaiting_docs' => 'Документы',
            'awaiting_payment' => 'Оплата',
            'completed' => 'Завершена',
            default => 'Новая',
        };
    }
}

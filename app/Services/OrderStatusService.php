<?php

namespace App\Services;

use App\Models\Order;

class OrderStatusService
{
    public function __construct(
        private readonly OrderDocumentRequirementService $orderDocumentRequirementService
    ) {}

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     messages: list<string>,
     *     required_documents_completed: bool,
     *     customer_paid: bool,
     *     carrier_paid: bool,
     *     manager_paid: bool
     * }
     */
    public function describe(Order $order, ?string $requestedStatus = null): array
    {
        $checklist = $this->orderDocumentRequirementService->checklistForOrder($order);
        $requiredDocumentsCompleted = collect($checklist)->every(
            fn (array $item): bool => (bool) ($item['completed'] ?? false)
        );
        $customerPaid = $this->isPaid($order, 'customer');
        $carrierPaid = $this->isPaid($order, 'carrier');
        $managerPaid = $this->isPaid($order, 'manager');
        $status = $this->resolveStatus(
            $order,
            $requestedStatus,
            $requiredDocumentsCompleted,
            $customerPaid,
            $carrierPaid,
            $managerPaid
        );

        return [
            'status' => $status,
            'label' => $this->label($status),
            'messages' => $this->messages($order, $checklist, $customerPaid, $carrierPaid, $managerPaid),
            'required_documents_completed' => $requiredDocumentsCompleted,
            'customer_paid' => $customerPaid,
            'carrier_paid' => $carrierPaid,
            'manager_paid' => $managerPaid,
        ];
    }

    public function resolve(Order $order, ?string $requestedStatus = null): string
    {
        return $this->describe($order, $requestedStatus)['status'];
    }

    public function label(string $status): string
    {
        return match ($status) {
            'new' => 'Новый заказ',
            'in_progress' => 'Выполняется',
            'documents' => 'Документы',
            'payment' => 'Оплата',
            'closed' => 'Закрыта',
            'cancelled' => 'Отменена',
            default => 'Новый заказ',
        };
    }

    /**
     * @param  list<array{
     *     key: string,
     *     label: string,
     *     completed: bool
     * }>  $checklist
     * @return list<string>
     */
    private function messages(
        Order $order,
        array $checklist,
        bool $customerPaid,
        bool $carrierPaid,
        bool $managerPaid
    ): array {
        $messages = [];

        $missingDocuments = collect($checklist)
            ->reject(fn (array $item): bool => (bool) ($item['completed'] ?? false))
            ->pluck('label')
            ->filter()
            ->values()
            ->all();

        if ($missingDocuments !== []) {
            $messages[] = 'Не хватает документов: '.implode(', ', $missingDocuments);
        }

        if ($order->unloading_date !== null && ! $customerPaid) {
            $messages[] = 'Нет отметки об оплате от заказчика.';
        }

        if ($order->unloading_date !== null && ! $carrierPaid) {
            $messages[] = 'Нет отметки об оплате перевозчику.';
        }

        if (($customerPaid || $carrierPaid) && ! $managerPaid) {
            $messages[] = 'Нет отметки о выплате менеджеру.';
        }

        return $messages;
    }

    private function resolveStatus(
        Order $order,
        ?string $requestedStatus,
        bool $requiredDocumentsCompleted,
        bool $customerPaid,
        bool $carrierPaid,
        bool $managerPaid
    ): string {
        if ($requestedStatus === 'cancelled') {
            return 'cancelled';
        }

        if (
            $order->unloading_date !== null
            && $requiredDocumentsCompleted
            && $customerPaid
            && $carrierPaid
            && $managerPaid
        ) {
            return 'closed';
        }

        if ($order->unloading_date !== null && $requiredDocumentsCompleted) {
            return 'payment';
        }

        if ($order->unloading_date !== null) {
            return 'documents';
        }

        if ($order->loading_date !== null && $this->hasExecutionRequests($order)) {
            return 'in_progress';
        }

        return 'new';
    }

    private function hasExecutionRequests(Order $order): bool
    {
        $checklist = collect($this->orderDocumentRequirementService->checklistForOrder($order))
            ->keyBy('key');

        return (bool) data_get($checklist->get('customer_request'), 'completed', false)
            && (bool) data_get($checklist->get('carrier_request'), 'completed', false);
    }

    private function isPaid(Order $order, string $party): bool
    {
        if ($party === 'manager') {
            return (float) ($order->salary_paid ?? 0) > 0
                || $this->extractPaidMarker((array) ($order->payment_statuses ?? []), 'manager');
        }

        return $this->extractPaidMarker((array) ($order->payment_statuses ?? []), $party);
    }

    /**
     * @param  array<string, mixed>  $paymentStatuses
     */
    private function extractPaidMarker(array $paymentStatuses, string $party): bool
    {
        $payload = $paymentStatuses[$party] ?? null;

        if (is_bool($payload)) {
            return $payload;
        }

        if (is_string($payload)) {
            return in_array($payload, ['paid', 'completed', 'true', '1'], true);
        }

        if (! is_array($payload)) {
            return false;
        }

        $status = data_get($payload, 'status');

        return (bool) data_get($payload, 'paid', false)
            || (bool) data_get($payload, 'is_paid', false)
            || filled(data_get($payload, 'paid_at'))
            || in_array($status, ['paid', 'completed'], true);
    }
}

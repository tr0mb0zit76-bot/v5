<?php

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonInterface;

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
        $milestones = $this->routeActualMilestones($order);
        $actualLoadingAt = $milestones['actual_loading'];
        $actualUnloadingAt = $milestones['actual_unloading'];

        $checklist = $this->orderDocumentRequirementService->checklistForOrder($order);
        $requiredDocumentsCompleted = collect($checklist)->every(
            fn (array $item): bool => (bool) ($item['completed'] ?? false)
        );
        $customerPaid = $this->isPaid($order, 'customer');
        $carrierPaid = $this->isPaid($order, 'carrier');
        $managerPaid = $this->isPaid($order, 'manager');
        $status = $this->resolveStatus(
            $requestedStatus,
            $actualLoadingAt,
            $actualUnloadingAt,
            $requiredDocumentsCompleted,
            $customerPaid,
            $carrierPaid,
            $managerPaid
        );

        return [
            'status' => $status,
            'label' => $this->label($status),
            'messages' => $this->messages($checklist, $actualUnloadingAt, $customerPaid, $carrierPaid, $managerPaid),
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
            'closed' => 'Завершено',
            'cancelled' => 'Отменена',
            default => 'Новый заказ',
        };
    }

    /**
     * Первая фактическая погрузка и последняя фактическая выгрузка по точкам маршрута.
     * Плановые даты не учитываются. Без точек маршрута — колонки заказа (legacy).
     *
     * @return array{actual_loading: ?CarbonInterface, actual_unloading: ?CarbonInterface}
     */
    private function routeActualMilestones(Order $order): array
    {
        if (! $order->relationLoaded('legs')) {
            $order->loadMissing([
                'legs' => fn ($q) => $q->orderBy('sequence'),
                'legs.routePoints' => fn ($q) => $q->orderBy('sequence'),
            ]);
        }

        $hasRoutePoints = $order->legs->contains(
            fn ($leg): bool => $leg->routePoints->isNotEmpty()
        );

        if (! $hasRoutePoints) {
            return [
                'actual_loading' => $order->loading_date,
                'actual_unloading' => $order->unloading_date,
            ];
        }

        $firstActualLoading = null;
        $lastActualUnloading = null;

        foreach ($order->legs as $leg) {
            foreach ($leg->routePoints as $point) {
                if ($point->type === 'loading' && $point->actual_date !== null) {
                    $firstActualLoading = $point->actual_date;

                    break 2;
                }
            }
        }

        foreach ($order->legs as $leg) {
            foreach ($leg->routePoints as $point) {
                if ($point->type === 'unloading' && $point->actual_date !== null) {
                    $lastActualUnloading = $point->actual_date;
                }
            }
        }

        return [
            'actual_loading' => $firstActualLoading,
            'actual_unloading' => $lastActualUnloading,
        ];
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
        array $checklist,
        ?CarbonInterface $actualUnloadingAt,
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

        if ($actualUnloadingAt !== null && ! $customerPaid) {
            $messages[] = 'Нет отметки об оплате от заказчика.';
        }

        if ($actualUnloadingAt !== null && ! $carrierPaid) {
            $messages[] = 'Нет отметки об оплате перевозчику.';
        }

        if (($customerPaid || $carrierPaid) && ! $managerPaid) {
            $messages[] = 'Нет отметки о выплате менеджеру.';
        }

        return $messages;
    }

    private function resolveStatus(
        ?string $requestedStatus,
        ?CarbonInterface $actualLoadingAt,
        ?CarbonInterface $actualUnloadingAt,
        bool $requiredDocumentsCompleted,
        bool $customerPaid,
        bool $carrierPaid,
        bool $managerPaid
    ): string {
        if ($requestedStatus === 'cancelled') {
            return 'cancelled';
        }

        if (
            $actualUnloadingAt !== null
            && $requiredDocumentsCompleted
            && $customerPaid
            && $carrierPaid
            && $managerPaid
        ) {
            return 'closed';
        }

        if ($actualUnloadingAt !== null && $requiredDocumentsCompleted) {
            return 'payment';
        }

        if ($actualUnloadingAt !== null) {
            return 'documents';
        }

        if ($actualLoadingAt !== null) {
            return 'in_progress';
        }

        return 'new';
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

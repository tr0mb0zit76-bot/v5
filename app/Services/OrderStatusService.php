<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Support\OrderManagerSalaryPaymentResolver;
use App\Support\OrderPartyPaymentSettlementResolver;
use App\Support\PerformerRouteActualDates;
use App\Support\RoutePointActualMilestones;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

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
        $milestones = RoutePointActualMilestones::forOrder($order);
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

    public function syncStoredStatus(Order $order, ?int $userId = null): string
    {
        $order->loadMissing(['legs.routePoints', 'documents', 'edoAcknowledgements']);

        $previousStatus = (string) $order->status;
        $derivedStatus = $this->resolve($order, $order->manual_status ?? null);

        if ($previousStatus === $derivedStatus) {
            return $derivedStatus;
        }

        $order->forceFill([
            'status' => $derivedStatus,
            'status_updated_by' => $userId,
            'status_updated_at' => now(),
            'is_active' => ! in_array($derivedStatus, ['closed', 'cancelled', 'disruption'], true),
        ])->save();

        if (Schema::hasTable('order_status_logs')) {
            OrderStatusLog::query()->create([
                'order_id' => $order->id,
                'status_from' => $previousStatus,
                'status_to' => $derivedStatus,
                'comment' => null,
                'created_by' => $userId,
            ]);
        }

        return $derivedStatus;
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
            'disruption' => 'Срыв',
            default => 'Новый заказ',
        };
    }

    /**
     * Факт старта перевозки: есть точка маршрута «погрузка» с заполненной фактической датой.
     * Без точек маршрута дату в карточке заказа не используем — план/факт не различимы.
     */
    public function hasFactOfLoadingOnRoute(Order $order): bool
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

        $performers = is_array($order->performers) ? $order->performers : [];
        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            if (PerformerRouteActualDates::performerHasLoadingActual($performer)) {
                return true;
            }
        }

        if (! $hasRoutePoints) {
            return false;
        }

        foreach ($order->legs as $leg) {
            foreach ($leg->routePoints as $point) {
                if ($point->type === 'loading' && $point->actual_date !== null) {
                    return true;
                }
            }
        }

        return false;
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

        if ($requestedStatus === 'disruption') {
            return 'disruption';
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
            if ((float) ($order->salary_paid ?? 0) > 0) {
                return true;
            }

            if ($this->extractPaidMarker((array) ($order->payment_statuses ?? []), 'manager')) {
                return true;
            }

            return OrderManagerSalaryPaymentResolver::isManagerSalaryPaid($order);
        }

        if ($this->extractPaidMarker((array) ($order->payment_statuses ?? []), $party)) {
            return true;
        }

        return OrderPartyPaymentSettlementResolver::isPartyFullyPaid($order, $party);
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

<?php

namespace App\Services\Mcp;

use App\Models\Order;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Services\Orders\OrderInlineFieldUpdateService;
use App\Support\ActivityEventType;
use App\Support\OrderInlineFieldCatalog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
        private readonly ActivityLedgerService $activityLedger,
        private readonly OrderInlineFieldUpdateService $inlineFieldUpdate,
    ) {}

    /**
     * @return array{orders: list<array<string, mixed>>, total: int}
     */
    public function search(User $user, string $query, int $limit = 15): array
    {
        $this->access->requireOrdersArea($user);

        $needle = trim($query);
        $limit = max(1, min($limit, 25));

        $builder = Order::query()
            ->with([
                'client:id,name',
                'carrier:id,name',
                'manager:id,name',
            ])
            ->orderByDesc('id');

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $builder->whereNull('deleted_at');
        }

        $this->access->applyOrdersScope($builder, $user);

        if ($needle !== '') {
            $builder->where(function (Builder $scoped) use ($needle): void {
                $scoped->where('order_number', 'like', '%'.$needle.'%');

                if (Schema::hasColumn('orders', 'order_customer_number')) {
                    $scoped->orWhere('order_customer_number', 'like', '%'.$needle.'%');
                }

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $scoped->orWhere('id', (int) $needle);
                }
            });
        }

        $orders = $builder->limit($limit)->get();

        return [
            'orders' => $orders->map(fn (Order $order): array => $this->summarize($order, $user))->all(),
            'total' => $orders->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(User $user, int $orderId): array
    {
        $this->access->requireOrdersArea($user);

        $builder = Order::query()
            ->with([
                'client:id,name',
                'carrier:id,name',
                'manager:id,name',
            ]);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $builder->whereNull('deleted_at');
        }

        $this->access->applyOrdersScope($builder, $user);

        /** @var Order $order */
        $order = $builder->whereKey($orderId)->firstOrFail();

        return $this->detail($order, $user);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Order $order, User $user): array
    {
        $payload = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->manual_status ?? $order->status,
            'order_date' => $order->order_date?->toDateString(),
            'loading_date' => $order->loading_date?->toDateString(),
            'unloading_date' => $order->unloading_date?->toDateString(),
            'customer_name' => $order->client?->name,
            'carrier_name' => $order->carrier?->name,
            'manager_name' => $order->manager?->name,
            'is_active' => (bool) $order->is_active,
        ];

        if ($this->access->canViewFinance($user)) {
            $payload['customer_rate'] = $order->customer_rate;
            $payload['delta'] = $order->delta;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Order $order, User $user): array
    {
        $summary = $this->summarize($order, $user);

        $summary['customer_id'] = $order->customer_id;
        $summary['carrier_id'] = $order->carrier_id;
        $summary['manager_id'] = $order->manager_id;
        $summary['lead_id'] = $order->lead_id;
        $summary['is_international_transport'] = (bool) $order->is_international_transport;
        $summary['payment_status'] = $order->payment_status;
        $summary['special_notes'] = $order->special_notes;
        $summary['customer_contact_name'] = $order->customer_contact_name;
        $summary['customer_contact_phone'] = $order->customer_contact_phone;
        $summary['track_number_customer'] = $order->track_number_customer;
        $summary['track_number_carrier'] = $order->track_number_carrier;

        if ($this->access->canViewFinance($user)) {
            $summary['carrier_rate'] = $order->carrier_rate;
            $summary['additional_expenses'] = $order->additional_expenses;
            $summary['insurance'] = $order->insurance;
            $summary['bonus'] = $order->bonus;
            $summary['kpi_percent'] = $order->kpi_percent;
            $summary['salary_accrued'] = $order->salary_accrued;
            $summary['salary_paid'] = $order->salary_paid;
        }

        return $summary;
    }

    /**
     * @return array{note: array<string, mixed>, order_id: int}
     */
    public function addNote(User $user, int $orderId, string $body, ?string $title = null): array
    {
        $order = $this->access->findAccessibleOrder($user, $orderId);

        $text = trim($body);
        if ($text === '') {
            throw ValidationException::withMessages([
                'body' => 'Текст заметки не может быть пустым.',
            ]);
        }

        if (mb_strlen($text) > 5000) {
            throw ValidationException::withMessages([
                'body' => 'Заметка не может быть длиннее 5000 символов.',
            ]);
        }

        $noteTitle = filled($title) ? trim((string) $title) : 'Заметка';

        $event = $this->activityLedger->record(
            $order,
            ActivityEventType::NoteAdded,
            $noteTitle,
            $text,
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'source' => 'ai_assistant',
            ],
            null,
            $user,
        );

        return [
            'order_id' => $order->id,
            'note' => $event !== null
                ? $this->activityLedger->serializeEvent($event)
                : [
                    'event_type' => ActivityEventType::NoteAdded->value,
                    'title' => $noteTitle,
                    'summary' => $text,
                ],
        ];
    }

    /**
     * @return array{order: array<string, mixed>, field: string, value: mixed}
     */
    public function updateField(User $user, int $orderId, string $field, mixed $value): array
    {
        $order = $this->access->findAccessibleOrder($user, $orderId);
        $this->access->ensureCanEditOrder($user, $order);

        if (OrderInlineFieldCatalog::isFinancialField($field) && ! $this->access->canViewFinance($user)) {
            throw new AuthenticationException('Нет доступа к финансовым полям заказа.');
        }

        OrderInlineFieldCatalog::validate($user, $order, $field, $value);
        $payload = OrderInlineFieldCatalog::normalizePayload($field, $value);

        $updated = $this->inlineFieldUpdate->apply(
            $user,
            $order,
            $payload['field'],
            $payload['value'],
        );

        return [
            'order_id' => $updated->id,
            'field' => $payload['field'],
            'value' => $payload['value'],
            'order' => $this->detail($updated->fresh([
                'client:id,name',
                'carrier:id,name',
                'manager:id,name',
            ]) ?? $updated, $user),
        ];
    }
}

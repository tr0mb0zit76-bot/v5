<?php

namespace App\Services\Mcp;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class OrderMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
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
}

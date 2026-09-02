<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\User;
use App\Support\OrderViewAuthorization;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Query Builder для грида заказов (бывший DB:: в OrderIndexController).
 * Фильтры AG Grid на клиенте — здесь только visibility scope + soft-delete.
 */
final class OrderIndexGridQueryService
{
    /**
     * @return Collection<int, object>
     */
    public function fetchRows(?User $user): Collection
    {
        $orderSelectColumns = $this->orderSelectColumns();

        return DB::table('orders')
            ->leftJoin('users as managers', 'managers.id', '=', 'orders.manager_id')
            ->leftJoin('contractors as customers', 'customers.id', '=', 'orders.customer_id')
            ->leftJoin('contractors as carriers', 'carriers.id', '=', 'orders.carrier_id')
            ->select($orderSelectColumns)
            ->selectSub($this->routePointSubquery('loading'), 'loading_point')
            ->selectSub($this->routePointSubquery('unloading', last: true), 'unloading_point')
            ->selectSub($this->routePointSubquery('unloading', last: true), 'last_unloading_point')
            ->selectSub($this->cargoDescriptionSubquery(), 'cargo_description')
            ->when(
                Schema::hasTable('leg_contractor_assignments'),
                fn ($query) => $query->selectSub($this->assignedCarrierCountSubquery(), 'assigned_carrier_count'),
            )
            ->when(
                $user !== null,
                function ($query) use ($user): void {
                    OrderViewAuthorization::applyOrdersVisibilityScopeToQuery($query, $user, 'orders');
                },
            )
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('orders.deleted_at')
            )
            ->orderBy('orders.id')
            ->get();
    }

    /**
     * @param  list<int>  $orderIds
     * @return Collection<int, string>
     */
    public function assignedCarrierNamesByOrderIds(array $orderIds): Collection
    {
        if ($orderIds === [] || ! Schema::hasTable('leg_contractor_assignments')) {
            return collect();
        }

        $rows = DB::table('order_legs')
            ->join('leg_contractor_assignments as lca', 'lca.order_leg_id', '=', 'order_legs.id')
            ->join('contractors as lcc', 'lcc.id', '=', 'lca.contractor_id')
            ->whereIn('order_legs.order_id', $orderIds)
            ->orderBy('lcc.name')
            ->select(['order_legs.order_id', 'lcc.name'])
            ->get();

        /** @var Collection<int, Collection<int, mixed>> $grouped */
        $grouped = $rows->groupBy('order_id');

        return $grouped->map(function (Collection $names): string {
            return $names->pluck('name')->unique()->filter()->values()->implode(' · ');
        });
    }

    /**
     * @return list<string|Expression>
     */
    private function orderSelectColumns(): array
    {
        $orderSelectColumns = [
            'orders.id',
            'orders.order_number',
            'orders.company_code',
            'orders.manager_id',
            'managers.name as manager_name',
            'orders.order_date',
            'orders.loading_date',
            'orders.unloading_date',
            'orders.customer_id',
            'customers.name as customer_name',
            'orders.customer_payment_form',
            'orders.customer_payment_term',
            'orders.carrier_id',
            'carriers.name as carrier_name',
            'orders.driver_id',
            'orders.customer_rate',
            'orders.additional_expenses',
            'orders.insurance',
            'orders.bonus',
            'orders.delta',
            'orders.kpi_percent',
            'orders.salary_accrued',
            'orders.salary_paid',
            'orders.status',
            'orders.manual_status',
            'orders.status_updated_by',
            'orders.status_updated_at',
            'orders.is_active',
            'orders.ai_draft_id',
            'orders.ai_confidence',
            'orders.ati_load_id',
            'orders.ati_published_at',
            DB::raw('COALESCE(orders.manual_status, orders.status) as status_text'),
            'orders.invoice_number',
            'orders.upd_number',
            'orders.waybill_number',
            'orders.track_number_customer',
            'orders.track_sent_date_customer',
            'orders.track_received_date_customer',
            'orders.track_number_carrier',
            'orders.track_sent_date_carrier',
            'orders.track_received_date_carrier',
            'orders.order_customer_number',
            'orders.order_customer_date',
            'orders.order_carrier_number',
            'orders.order_carrier_date',
            'orders.upd_carrier_number',
            'orders.upd_carrier_date',
            'orders.customer_contact_name',
            'orders.customer_contact_phone',
            'orders.customer_contact_email',
            'orders.carrier_contact_name',
            'orders.carrier_contact_phone',
            'orders.carrier_contact_email',
            'orders.created_by',
            'orders.updated_by',
            'orders.created_at',
            'orders.updated_at',
        ];

        if (Schema::hasColumn('orders', 'track_received_date_customer_request')) {
            $orderSelectColumns[] = 'orders.track_received_date_customer_request';
        }

        if (Schema::hasColumn('orders', 'track_received_date_customer_closing')) {
            $orderSelectColumns[] = 'orders.track_received_date_customer_closing';
        }

        if (Schema::hasColumn('orders', 'track_received_date_carrier_request')) {
            $orderSelectColumns[] = 'orders.track_received_date_carrier_request';
        }

        if (Schema::hasColumn('orders', 'track_received_date_carrier_closing')) {
            $orderSelectColumns[] = 'orders.track_received_date_carrier_closing';
        }

        if (Schema::hasColumn('orders', 'carrier_rate')) {
            $orderSelectColumns[] = 'orders.carrier_rate';
        }

        if (Schema::hasColumn('orders', 'carrier_payment_form')) {
            $orderSelectColumns[] = 'orders.carrier_payment_form';
        }

        if (Schema::hasColumn('orders', 'carrier_payment_term')) {
            $orderSelectColumns[] = 'orders.carrier_payment_term';
        }

        if (Schema::hasColumn('orders', 'performers')) {
            $orderSelectColumns[] = 'orders.performers';
        }

        return $orderSelectColumns;
    }

    private function routePointSubquery(string $type, bool $last = false): Builder
    {
        $cityCandidates = array_values(array_filter([
            Schema::hasColumn('route_points', 'normalized_data')
                ? "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(route_points.normalized_data, '$.city')), '')"
                : null,
            Schema::hasColumn('route_points', 'metadata')
                ? "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(route_points.metadata, '$.normalized_data.city')), '')"
                : null,
            'NULLIF(cities.name, "")',
        ]));
        $cityExpression = match (count($cityCandidates)) {
            0 => 'NULL',
            1 => $cityCandidates[0],
            default => 'COALESCE('.implode(', ', $cityCandidates).')',
        };

        $addressExpression = Schema::hasColumn('route_points', 'address')
            ? 'COALESCE(NULLIF(route_points.address, ""), NULLIF(cities.name, ""), addresses.address_line)'
            : 'COALESCE(NULLIF(cities.name, ""), addresses.address_line)';
        $displayExpression = "COALESCE({$cityExpression}, {$addressExpression})";

        $query = DB::table('route_points')
            ->join('order_legs', 'order_legs.id', '=', 'route_points.order_leg_id')
            ->leftJoin('addresses', 'addresses.id', '=', 'route_points.address_id')
            ->leftJoin('cities', 'cities.id', '=', 'addresses.city_id')
            ->selectRaw($displayExpression)
            ->whereColumn('order_legs.order_id', 'orders.id')
            ->where('route_points.type', $type);

        if ($last) {
            return $query
                ->orderByDesc('order_legs.sequence')
                ->orderByDesc('route_points.sequence')
                ->limit(1);
        }

        return $query
            ->orderBy('order_legs.sequence')
            ->orderBy('route_points.sequence')
            ->limit(1);
    }

    private function cargoDescriptionSubquery(): Builder
    {
        return DB::table('cargo_leg')
            ->join('order_legs', 'order_legs.id', '=', 'cargo_leg.order_leg_id')
            ->join('cargos', 'cargos.id', '=', 'cargo_leg.cargo_id')
            ->selectRaw('COALESCE(NULLIF(cargos.title, ""), cargos.description)')
            ->whereColumn('order_legs.order_id', 'orders.id')
            ->orderBy('order_legs.sequence')
            ->limit(1);
    }

    private function assignedCarrierCountSubquery(): Builder
    {
        return DB::table('order_legs')
            ->join('leg_contractor_assignments as lca', 'lca.order_leg_id', '=', 'order_legs.id')
            ->whereColumn('order_legs.order_id', 'orders.id')
            ->selectRaw('COUNT(DISTINCT lca.contractor_id)');
    }
}

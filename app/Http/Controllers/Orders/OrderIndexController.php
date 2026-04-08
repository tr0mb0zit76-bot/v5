<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Support\OrderTableColumns;
use App\Support\RoleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class OrderIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = $this->resolveRole($user?->role_id);
        $roleName = $role['name'];
        $ordersScope = RoleAccess::resolveVisibilityScope($roleName, $role['visibility_scopes'], 'orders');

        $orderSelectColumns = [
            'orders.id',
            'orders.order_number',
            'orders.company_code',
            'orders.manager_id',
            'managers.name as manager_name',
            'orders.site_id',
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
            'orders.ai_metadata',
            'orders.ati_response',
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
            'orders.metadata',
            'orders.payment_statuses',
            'orders.created_at',
            'orders.updated_at',
        ];

        if (Schema::hasColumn('orders', 'carrier_rate')) {
            $orderSelectColumns[] = 'orders.carrier_rate';
        }

        $rows = DB::table('orders')
            ->leftJoin('users as managers', 'managers.id', '=', 'orders.manager_id')
            ->leftJoin('contractors as customers', 'customers.id', '=', 'orders.customer_id')
            ->leftJoin('contractors as carriers', 'carriers.id', '=', 'orders.carrier_id')
            ->select($orderSelectColumns)
            ->selectSub($this->routePointSubquery('loading'), 'loading_point')
            ->selectSub($this->routePointSubquery('unloading'), 'unloading_point')
            ->selectSub($this->cargoDescriptionSubquery(), 'cargo_description')
            ->when(
                Schema::hasTable('leg_contractor_assignments'),
                fn ($query) => $query->selectSub($this->assignedCarrierCountSubquery(), 'assigned_carrier_count'),
            )
            ->when(
                $user !== null && $roleName !== 'admin' && $ordersScope !== 'all',
                function ($query) use ($user) {
                    $query->where('orders.manager_id', $user->id);
                }
            )
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('orders.deleted_at')
            )
            ->orderBy('orders.id')
            ->get();

        $carrierRateFromFinancialByOrderId = $this->carrierRatesFromFinancialTerms(
            $rows->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        $assignmentNamesByOrderId = Schema::hasTable('leg_contractor_assignments')
            ? $this->assignedCarrierNamesByOrderIds($rows->pluck('id')->map(fn ($id): int => (int) $id)->all())
            : collect();

        $rows = $rows->map(function ($order) use ($roleName, $user, $assignmentNamesByOrderId, $carrierRateFromFinancialByOrderId): array {
            $row = (array) $order;
            $assignmentNames = (string) ($assignmentNamesByOrderId->get((int) $order->id) ?? '');
            $row = $this->applyAssignedCarrierDisplay($row, $assignmentNames);

            $computedCarrierRate = $carrierRateFromFinancialByOrderId->get((int) $order->id);
            if ($computedCarrierRate !== null) {
                $row['carrier_rate'] = $computedCarrierRate;
            } elseif (! array_key_exists('carrier_rate', $row)) {
                $row['carrier_rate'] = null;
            }

            return [
                ...$row,
                'can_delete' => $this->canDeleteOrder($row, $roleName, $user?->id),
            ];
        });

        return Inertia::render('Orders/Index', [
            'rows' => $rows,
            'roleKey' => $roleName ?? 'manager',
            'orderColumns' => OrderTableColumns::options(),
        ]);
    }

    /**
     * @return array{name: string|null, visibility_scopes: array<string, string>}
     */
    /**
     * Сумма сумм по плечам из `financial_terms.contractors_costs` — после миграции колонка `orders.carrier_rate` может отсутствовать.
     *
     * @param  list<int>  $orderIds
     * @return Collection<int, float>
     */
    private function carrierRatesFromFinancialTerms(array $orderIds): Collection
    {
        if ($orderIds === [] || ! Schema::hasTable('financial_terms')) {
            return collect();
        }

        $rows = DB::table('financial_terms')
            ->whereIn('order_id', $orderIds)
            ->get(['order_id', 'contractors_costs']);

        return $rows->mapWithKeys(function (object $row): array {
            $sum = $this->sumJsonContractorsCostsAmounts($row->contractors_costs);

            return [(int) $row->order_id => $sum];
        });
    }

    private function sumJsonContractorsCostsAmounts(mixed $payload): ?float
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        if (is_array($payload)) {
            $costs = $payload;
        } else {
            $decoded = json_decode((string) $payload, true);
            $costs = is_array($decoded) ? $decoded : [];
        }

        if ($costs === []) {
            return null;
        }

        $sum = collect($costs)->sum(fn (array $c): float => (float) ($c['amount'] ?? 0));

        return round($sum, 2);
    }

    /**
     * @return array{name: string|null, visibility_scopes: array<string, string>}
     */
    private function resolveRole(?int $roleId): array
    {
        if ($roleId === null) {
            return [
                'name' => null,
                'visibility_scopes' => [],
            ];
        }

        $select = ['name'];

        if (Schema::hasColumn('roles', 'visibility_scopes')) {
            $select[] = 'visibility_scopes';
        }

        $role = DB::table('roles')
            ->where('id', $roleId)
            ->select($select)
            ->first();

        if ($role === null) {
            return [
                'name' => null,
                'visibility_scopes' => [],
            ];
        }

        $visibilityScopes = property_exists($role, 'visibility_scopes')
            ? $role->visibility_scopes
            : [];

        if (is_string($visibilityScopes)) {
            $visibilityScopes = json_decode($visibilityScopes, true);
        }

        return [
            'name' => $role->name,
            'visibility_scopes' => is_array($visibilityScopes) ? $visibilityScopes : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function canDeleteOrder(array $order, ?string $roleName, ?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        if (in_array($roleName, ['admin', 'supervisor'], true)) {
            return true;
        }

        if ($roleName !== 'manager') {
            return false;
        }

        return (int) ($order['manager_id'] ?? 0) === $userId
            && empty($order['loading_date']);
    }

    private function routePointSubquery(string $type)
    {
        $addressExpression = Schema::hasColumn('route_points', 'address')
            ? 'COALESCE(NULLIF(route_points.address, ""), NULLIF(cities.name, ""), addresses.address_line)'
            : 'COALESCE(NULLIF(cities.name, ""), addresses.address_line)';

        return DB::table('route_points')
            ->join('order_legs', 'order_legs.id', '=', 'route_points.order_leg_id')
            ->leftJoin('addresses', 'addresses.id', '=', 'route_points.address_id')
            ->leftJoin('cities', 'cities.id', '=', 'addresses.city_id')
            ->selectRaw($addressExpression)
            ->whereColumn('order_legs.order_id', 'orders.id')
            ->where('route_points.type', $type)
            ->orderBy('order_legs.sequence')
            ->orderBy('route_points.sequence')
            ->limit(1);
    }

    private function cargoDescriptionSubquery()
    {
        return DB::table('cargo_leg')
            ->join('order_legs', 'order_legs.id', '=', 'cargo_leg.order_leg_id')
            ->join('cargos', 'cargos.id', '=', 'cargo_leg.cargo_id')
            ->selectRaw('COALESCE(NULLIF(cargos.title, ""), cargos.description)')
            ->whereColumn('order_legs.order_id', 'orders.id')
            ->orderBy('order_legs.sequence')
            ->limit(1);
    }

    private function assignedCarrierCountSubquery()
    {
        return DB::table('order_legs')
            ->join('leg_contractor_assignments as lca', 'lca.order_leg_id', '=', 'order_legs.id')
            ->whereColumn('order_legs.order_id', 'orders.id')
            ->selectRaw('COUNT(DISTINCT lca.contractor_id)');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyAssignedCarrierDisplay(array $row, string $assignmentNames): array
    {
        if (! Schema::hasTable('leg_contractor_assignments')) {
            return $row;
        }

        $count = (int) ($row['assigned_carrier_count'] ?? 0);

        if ($count <= 1) {
            if ($count === 1 && $assignmentNames !== '') {
                $row['carrier_name'] = $assignmentNames;
            }

            return $row;
        }

        $row['carrier_name'] = $count.' перевозчиков';
        $row['carrier_name_tooltip'] = $assignmentNames !== '' ? $assignmentNames : null;

        return $row;
    }

    /**
     * @param  list<int>  $orderIds
     * @return Collection<int, string>
     */
    private function assignedCarrierNamesByOrderIds(array $orderIds): Collection
    {
        if ($orderIds === []) {
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
}

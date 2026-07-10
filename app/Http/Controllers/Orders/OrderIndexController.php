<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Support\CarrierPaymentFormResolver;
use App\Support\CarrierPaymentTermResolver;
use App\Support\CarrierRateFromFinancialTerms;
use App\Support\OrderDeleteAuthorization;
use App\Support\OrderFinancialEditAuthorization;
use App\Support\OrderGridOneCSummaryResolver;
use App\Support\OrderTableColumns;
use App\Support\OrderViewAuthorization;
use App\Support\PaymentFormDictionary;
use App\Support\RoleAccess;
use App\Support\RoutePointDatesDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class OrderIndexController extends Controller
{
    public function __invoke(Request $request, OrderGridOneCSummaryResolver $oneCSummaryResolver): Response
    {
        $user = $request->user();
        $role = $this->resolveRole($user?->role_id);
        $roleName = $role['name'];

        // Крупный JSON (ai_metadata, ati_response, metadata, payment_statuses) не выбираем — только карточка заказа.

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

        $rows = DB::table('orders')
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

        $carrierRateFromFinancialByOrderId = CarrierRateFromFinancialTerms::sumsByOrderId(
            $rows->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        $carrierPaymentFormByOrderId = CarrierPaymentFormResolver::mapForOrderIds(
            $rows->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        $carrierPaymentTermByOrderId = CarrierPaymentTermResolver::mapForOrderIds(
            $rows->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        $routePointDatesByOrderId = RoutePointDatesDisplay::mapForOrderIds(
            $rows->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        $assignmentNamesByOrderId = Schema::hasTable('leg_contractor_assignments')
            ? $this->assignedCarrierNamesByOrderIds($rows->pluck('id')->map(fn ($id): int => (int) $id)->all())
            : collect();

        $rows = $rows->map(function ($order) use ($roleName, $user, $assignmentNamesByOrderId, $carrierRateFromFinancialByOrderId, $carrierPaymentFormByOrderId, $carrierPaymentTermByOrderId, $routePointDatesByOrderId): array {
            $row = (array) $order;
            $assignmentNames = (string) ($assignmentNamesByOrderId->get((int) $order->id) ?? '');
            $row = $this->applyAssignedCarrierDisplay($row, $assignmentNames);

            $dbLoadingDate = $row['loading_date'] ?? null;
            $dbUnloadingDate = $row['unloading_date'] ?? null;

            $route = $routePointDatesByOrderId->get((int) $order->id);
            if ($route === null) {
                $route = [
                    'loading_display' => null,
                    'unloading_display' => null,
                    'loading_kind' => 'none',
                    'unloading_kind' => 'none',
                ];
            }

            $loadingDisplay = $route['loading_display'] ?? $dbLoadingDate;
            $unloadingDisplay = $route['unloading_display'] ?? $dbUnloadingDate;
            $loadingKind = $route['loading_kind'];
            $unloadingKind = $route['unloading_kind'];
            if ($loadingKind === 'none' && filled($dbLoadingDate)) {
                $loadingKind = 'order';
            }
            if ($unloadingKind === 'none' && filled($dbUnloadingDate)) {
                $unloadingKind = 'order';
            }

            $row['loading_date'] = $loadingDisplay;
            $row['unloading_date'] = $unloadingDisplay;
            $row['loading_date_route_kind'] = $loadingKind;
            $row['unloading_date_route_kind'] = $unloadingKind;

            $computedCarrierRate = $carrierRateFromFinancialByOrderId->get((int) $order->id);
            if ($computedCarrierRate !== null) {
                $row['carrier_rate'] = $computedCarrierRate;
            } elseif (! array_key_exists('carrier_rate', $row)) {
                $row['carrier_rate'] = null;
            }

            $computedCarrierPaymentForm = $carrierPaymentFormByOrderId->get((int) $order->id);
            $dbCarrierPaymentForm = $row['carrier_payment_form'] ?? null;
            $row['carrier_payment_form'] = $computedCarrierPaymentForm !== null
                ? $computedCarrierPaymentForm
                : $dbCarrierPaymentForm;

            $computedCarrierPaymentTerm = $carrierPaymentTermByOrderId->get((int) $order->id);
            $dbCarrierPaymentTerm = $row['carrier_payment_term'] ?? null;
            $row['carrier_payment_term'] = $computedCarrierPaymentTerm !== null
                ? $computedCarrierPaymentTerm
                : $dbCarrierPaymentTerm;

            return [
                ...$row,
                'can_delete' => OrderDeleteAuthorization::userMayDelete(
                    $roleName,
                    $user?->id,
                    (int) ($row['manager_id'] ?? 0),
                    $row['manual_status'] ?? null,
                    $row['status'] ?? null,
                ),
                'can_edit_financial_fields' => OrderFinancialEditAuthorization::userMayEditFinancialFieldsForRow(
                    $user,
                    $roleName,
                    (int) ($user?->id ?? 0),
                    (int) ($row['manager_id'] ?? 0),
                    $row['manual_status'] ?? null,
                    $row['status'] ?? null,
                ),
            ];
        });

        $rows = $oneCSummaryResolver->enrich(collect($rows))->values()->all();

        return Inertia::render('Orders/Index', [
            'rows' => $rows,
            'roleKey' => $roleName ?? 'manager',
            'orderColumns' => OrderTableColumns::options(),
            'orderInlineEditableFields' => RoleAccess::orderInlineEditableFieldsForUser($user),
            'paymentFormOptions' => PaymentFormDictionary::options(),
        ]);
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
            ? RoleAccess::coerceVisibilityScopes($role->visibility_scopes)
            : null;

        return [
            'name' => $role->name,
            'visibility_scopes' => is_array($visibilityScopes) ? $visibilityScopes : [],
        ];
    }

    private function routePointSubquery(string $type, bool $last = false)
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

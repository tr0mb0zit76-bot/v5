<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\Orders\OrderIndexGridQueryService;
use App\Support\CarrierPaymentFormResolver;
use App\Support\CarrierPaymentTermResolver;
use App\Support\CarrierRateFromFinancialTerms;
use App\Support\OrderDeleteAuthorization;
use App\Support\OrderFinancialEditAuthorization;
use App\Support\OrderGridOneCSummaryResolver;
use App\Support\OrderTableColumns;
use App\Support\PaymentFormDictionary;
use App\Support\RoleAccess;
use App\Support\RoutePointDatesDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class OrderIndexController extends Controller
{
    public function __invoke(
        Request $request,
        OrderGridOneCSummaryResolver $oneCSummaryResolver,
        OrderIndexGridQueryService $gridQuery,
    ): Response {
        $user = $request->user();
        $role = $this->resolveRole($user?->role_id);
        $roleName = $role['name'];

        // Крупный JSON (ai_metadata, ati_response, metadata, payment_statuses) не выбираем — только карточка заказа.
        $rows = $gridQuery->fetchRows($user);

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
            ? $gridQuery->assignedCarrierNamesByOrderIds($rows->pluck('id')->map(fn ($id): int => (int) $id)->all())
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

        $role = Role::query()
            ->where('id', $roleId)
            ->first($select);

        if ($role === null) {
            return [
                'name' => null,
                'visibility_scopes' => [],
            ];
        }

        $visibilityScopes = array_key_exists('visibility_scopes', $role->getAttributes())
            ? RoleAccess::coerceVisibilityScopes($role->visibility_scopes)
            : null;

        return [
            'name' => $role->name,
            'visibility_scopes' => is_array($visibilityScopes) ? $visibilityScopes : [],
        ];
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
}

<?php

namespace App\Http\Requests;

use App\Models\Contractor;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\Order;
use App\Models\User;
use App\Rules\DocumentWithinPageBudget;
use App\Services\ContractorCreditService;
use App\Support\CurrencyDictionary;
use App\Support\DocumentUploadBudget;
use App\Support\OrderCargoItemsPayloadNormalizer;
use App\Support\OrderDisruptionGuard;
use App\Support\OrderDocumentRegistryTypes;
use App\Support\OwnFleetCatalog;
use App\Support\PaymentFormDictionary;
use App\Support\PaymentInstallmentScheduleNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use JsonException;

class StoreOrderRequest extends FormRequest
{
    private const CONTRACT_TYPES = ['contract', 'contract_request'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('order_payload')) {
            try {
                /** @var array<string, mixed> $data */
                $data = json_decode($this->string('order_payload')->value(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages([
                    'order_payload' => 'Некорректный JSON заказа.',
                ]);
            }

            if (! is_array($data)) {
                throw ValidationException::withMessages([
                    'order_payload' => 'Некорректный JSON заказа.',
                ]);
            }

            $documents = $data['documents'] ?? [];
            if (is_array($documents)) {
                foreach (array_keys($documents) as $index) {
                    $uploadKey = 'document_file_'.$index;
                    if ($this->hasFile($uploadKey)) {
                        $documents[$index]['file'] = $this->file($uploadKey);
                    }
                }
                $data['documents'] = $documents;
            }

            $this->merge($data);
        }

        $this->normalizeCargoItemsPerformerAllocationsInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedForWizard(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $validated['cargo_items'] = OrderCargoItemsPayloadNormalizer::normalizeValidatedCargoItems($validated, $this);

        if (! array_key_exists('order_owner_id', $validated) && isset($validated['responsible_id'])) {
            $validated['order_owner_id'] = $validated['responsible_id'];
        }

        return $validated;
    }

    private function normalizeCargoItemsPerformerAllocationsInput(): void
    {
        $items = $this->input('cargo_items');
        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $allocations = $item['performer_allocations'] ?? null;
            if (! is_array($allocations)) {
                $atiPayload = $item['ati_cargo_payload'] ?? null;
                if (is_array($atiPayload) && ! array_is_list($atiPayload) && is_array($atiPayload['performer_allocations'] ?? null)) {
                    $allocations = $atiPayload['performer_allocations'];
                }
            }

            if (! is_array($allocations)) {
                continue;
            }

            foreach ($allocations as $rowIndex => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $slot = $row['carrier_slot'] ?? null;
                if ($slot === '' || $slot === 'null') {
                    $allocations[$rowIndex]['carrier_slot'] = null;
                }
            }

            $items[$index]['performer_allocations'] = $allocations;
        }

        $this->merge(['cargo_items' => $items]);
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return $this->baseRules();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('status') !== 'disruption') {
                    return;
                }

                if ($this->routeIs('orders.store')) {
                    $validator->errors()->add('status', 'Нельзя создать заказ сразу в статусе «Срыв».');

                    return;
                }

                if (! $this->routeIs('orders.update')) {
                    return;
                }

                $order = $this->route('order');
                $user = $this->user();

                if (! $order instanceof Order || ! $user instanceof User) {
                    return;
                }

                OrderDisruptionGuard::validateMarkDisrupted($user, $order, $validator, 'status');
            },
            function (Validator $validator): void {
                $dispatcherId = $this->input('dispatcher_id');
                if ($dispatcherId === null || $dispatcherId === '') {
                    return;
                }

                $ownerPercent = $this->input('compensation_owner_percent');
                $dispatcherPercent = $this->input('compensation_dispatcher_percent');

                if ($ownerPercent === null && $dispatcherPercent === null) {
                    return;
                }

                $owner = (float) ($ownerPercent ?? 0);
                $dispatcher = (float) ($dispatcherPercent ?? 0);

                if (abs(($owner + $dispatcher) - 100) > 0.01) {
                    $validator->errors()->add(
                        'compensation_owner_percent',
                        'Доли владельца и диспетчера должны в сумме давать 100%.',
                    );
                }
            },
            function (Validator $validator): void {
                if (! $this->routeIs('orders.store')) {
                    return;
                }

                $clientId = $this->integer('client_id');

                if ($clientId <= 0) {
                    return;
                }

                /** @var ContractorCreditService $creditService */
                $creditService = app(ContractorCreditService::class);

                if (! $creditService->supportsDebtLimit()) {
                    return;
                }

                $contractor = Contractor::query()->find($clientId);

                if ($contractor === null || ! $creditService->isBlockedByDebtLimit($contractor)) {
                    return;
                }

                $currency = $contractor->debt_limit_currency ?: 'RUB';
                $limit = number_format((float) $contractor->debt_limit, 2, '.', ' ');
                $debt = number_format($creditService->currentDebtForContractor($contractor->id), 2, '.', ' ');

                $validator->errors()->add(
                    'client_id',
                    "Лимит задолженности контрагента достигнут ({$debt} {$currency} из {$limit} {$currency}). Новые заказы заблокированы."
                );
            },
            function (Validator $validator): void {
                $performers = $this->input('performers', []);
                $performerCarrierIds = collect(is_array($performers) ? $performers : [])
                    ->filter(fn (mixed $item): bool => is_array($item))
                    ->flatMap(function (array $item): array {
                        if (($item['carrier_mode'] ?? 'single') === 'split' && is_array($item['split_carriers'] ?? null)) {
                            return collect($item['split_carriers'])
                                ->filter(fn (mixed $slot): bool => is_array($slot))
                                ->map(fn (array $slot): int => (int) ($slot['contractor_id'] ?? 0))
                                ->all();
                        }

                        return [(int) ($item['contractor_id'] ?? 0)];
                    });

                $contractorCosts = Arr::get($this->input('financial_term', []), 'contractors_costs', []);
                $costCarrierIds = collect(is_array($contractorCosts) ? $contractorCosts : [])
                    ->filter(fn (mixed $item): bool => is_array($item))
                    ->map(fn (array $item): int => (int) ($item['contractor_id'] ?? 0));

                $hasCarrier = $performerCarrierIds
                    ->merge($costCarrierIds)
                    ->contains(fn (int $id): bool => $id > 0);

                if (! $hasCarrier) {
                    $validator->errors()->add('performers', 'Укажите хотя бы одного перевозчика.');
                }

                foreach (is_array($performers) ? $performers : [] as $i => $performer) {
                    if (! is_array($performer) || ($performer['carrier_mode'] ?? 'single') !== 'split') {
                        continue;
                    }

                    $slots = is_array($performer['split_carriers'] ?? null) ? $performer['split_carriers'] : [];
                    if (count($slots) < 2) {
                        $validator->errors()->add(
                            "performers.$i.split_carriers",
                            'Для режима «Несколько исполнителей» укажите минимум двух перевозчиков на плече.'
                        );
                    }
                }

                $clientPrice = Arr::get($this->input('financial_term', []), 'client_price');
                if ($clientPrice === null || $clientPrice === '' || (float) $clientPrice <= 0) {
                    $validator->errors()->add('financial_term.client_price', 'Укажите цену клиента больше 0.');
                }
            },
            function (Validator $validator): void {
                if (! Schema::hasTable('fleet_vehicles') || ! Schema::hasTable('fleet_drivers')) {
                    return;
                }

                $performers = $this->input('performers', []);
                if (! is_array($performers)) {
                    return;
                }

                foreach ($performers as $i => $performer) {
                    if (! is_array($performer)) {
                        continue;
                    }

                    $fleetRows = (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null))
                        ? collect($performer['split_carriers'])
                            ->filter(fn (mixed $slot): bool => is_array($slot))
                            ->map(fn (array $slot, int $slotIndex): array => [
                                'prefix' => "performers.$i.split_carriers.$slotIndex",
                                'carrier_id' => isset($slot['contractor_id']) ? (int) $slot['contractor_id'] : null,
                                'vehicle_id' => isset($slot['fleet_vehicle_id']) ? (int) $slot['fleet_vehicle_id'] : null,
                                'driver_id' => isset($slot['fleet_driver_id']) ? (int) $slot['fleet_driver_id'] : null,
                            ])
                            ->all()
                        : [[
                            'prefix' => "performers.$i",
                            'carrier_id' => isset($performer['contractor_id']) ? (int) $performer['contractor_id'] : null,
                            'vehicle_id' => isset($performer['fleet_vehicle_id']) ? (int) $performer['fleet_vehicle_id'] : null,
                            'driver_id' => isset($performer['fleet_driver_id']) ? (int) $performer['fleet_driver_id'] : null,
                        ]];

                    foreach ($fleetRows as $row) {
                        $carrierId = $row['carrier_id'];
                        $vehicleId = $row['vehicle_id'];
                        $driverId = $row['driver_id'];
                        $prefix = $row['prefix'];

                        if ($vehicleId > 0) {
                            $vehicle = FleetVehicle::query()->find($vehicleId);
                            if ($vehicle === null) {
                                $validator->errors()->add("{$prefix}.fleet_vehicle_id", 'Транспортное средство не найдено.');
                            } elseif ($carrierId && (int) $vehicle->owner_contractor_id !== $carrierId) {
                                $validator->errors()->add("{$prefix}.fleet_vehicle_id", 'ТС должно принадлежать выбранному перевозчику (владелец в карточке ТС).');
                            }
                        }

                        if ($driverId > 0) {
                            $driver = FleetDriver::query()->find($driverId);
                            if ($driver === null) {
                                $validator->errors()->add("{$prefix}.fleet_driver_id", 'Водитель не найден.');
                            } elseif ($carrierId && (int) $driver->carrier_contractor_id !== $carrierId) {
                                $validator->errors()->add("{$prefix}.fleet_driver_id", 'Водитель должен быть привязан к выбранному контрагенту-перевозчику.');
                            }
                        }
                    }
                }
            },
            function (Validator $validator): void {
                $ft = $this->input('financial_term', []);
                if (! is_array($ft)) {
                    return;
                }

                $sched = $ft['client_payment_schedule'] ?? null;
                if (! is_array($sched)) {
                    return;
                }

                $sched = PaymentInstallmentScheduleNormalizer::ensureInstallmentModel($sched);
                /** @var list<array<string, mixed>> $rows */
                $rows = array_values(array_filter($sched['installments'] ?? [], static fn ($r): bool => is_array($r)));
                if ($rows === []) {
                    $validator->errors()->add('financial_term.client_payment_schedule.installments', 'Добавьте хотя бы одну траншу.');

                    return;
                }

                if (count($rows) > PaymentInstallmentScheduleNormalizer::MAX_INSTALLMENTS) {
                    $validator->errors()->add(
                        'financial_term.client_payment_schedule.installments',
                        'Не более '.PaymentInstallmentScheduleNormalizer::MAX_INSTALLMENTS.' траншей.',
                    );

                    return;
                }

                $sum = array_sum(array_map(static fn (array $r): float => (float) ($r['percent'] ?? 0), $rows));
                if (count($rows) >= 2 && abs($sum - 100.0) > 0.05) {
                    $validator->errors()->add('financial_term.client_payment_schedule.installments', 'Сумма процентов по траншам должна быть 100%.');
                }

                if (count($rows) === 1 && abs($sum - 100.0) > 0.05) {
                    $validator->errors()->add('financial_term.client_payment_schedule.installments', 'Для одной транши укажите 100%.');
                }
            },
            function (Validator $validator): void {
                $ft = $this->input('financial_term', []);
                if (! is_array($ft)) {
                    return;
                }

                $costs = Arr::get($ft, 'contractors_costs', []);
                if (! is_array($costs)) {
                    return;
                }

                foreach ($costs as $i => $cost) {
                    if (! is_array($cost)) {
                        continue;
                    }

                    $sched = $cost['payment_schedule'] ?? null;
                    if (! is_array($sched)) {
                        continue;
                    }

                    $sched = PaymentInstallmentScheduleNormalizer::ensureInstallmentModel($sched);
                    /** @var list<array<string, mixed>> $rows */
                    $rows = array_values(array_filter($sched['installments'] ?? [], static fn ($r): bool => is_array($r)));
                    $baseKey = "financial_term.contractors_costs.$i.payment_schedule.installments";

                    if ($rows === []) {
                        $validator->errors()->add($baseKey, 'Добавьте хотя бы одну траншу.');

                        continue;
                    }

                    if (count($rows) > PaymentInstallmentScheduleNormalizer::MAX_INSTALLMENTS) {
                        $validator->errors()->add(
                            $baseKey,
                            'Не более '.PaymentInstallmentScheduleNormalizer::MAX_INSTALLMENTS.' траншей.',
                        );

                        continue;
                    }

                    $sum = array_sum(array_map(static fn (array $r): float => (float) ($r['percent'] ?? 0), $rows));
                    if (count($rows) >= 2 && abs($sum - 100.0) > 0.05) {
                        $validator->errors()->add($baseKey, 'Сумма процентов по траншам должна быть 100%.');
                    }

                    if (count($rows) === 1 && abs($sum - 100.0) > 0.05) {
                        $validator->errors()->add($baseKey, 'Для одной транши укажите 100%.');
                    }
                }
            },
            function (Validator $validator): void {
                $rawId = $this->input('own_company_bank_account_id');
                if ($rawId === null || $rawId === '') {
                    return;
                }

                if (! is_string($rawId)) {
                    $validator->errors()->add('own_company_bank_account_id', 'Некорректный идентификатор счёта.');

                    return;
                }

                $accountId = trim($rawId);
                if ($accountId === '') {
                    return;
                }

                $ownCompanyId = (int) $this->input('own_company_id', 0);
                if ($ownCompanyId <= 0) {
                    $validator->errors()->add('own_company_bank_account_id', 'Сначала выберите свою компанию, чтобы указать расчётный счёт.');

                    return;
                }

                $contractor = Contractor::query()->find($ownCompanyId);
                if ($contractor === null) {
                    return;
                }

                if (Schema::hasColumn('contractors', 'is_own_company') && ! (bool) $contractor->is_own_company) {
                    $validator->errors()->add('own_company_bank_account_id', 'Счёт можно выбрать только для своей компании.');

                    return;
                }

                $accounts = $contractor->bank_accounts;
                if (! is_array($accounts)) {
                    $validator->errors()->add('own_company_bank_account_id', 'У выбранной компании нет списка счетов.');

                    return;
                }

                foreach ($accounts as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $rowId = $row['id'] ?? null;
                    if ($rowId !== null && (string) $rowId === $accountId) {
                        return;
                    }
                }

                $validator->errors()->add('own_company_bank_account_id', 'Указанный счёт не найден у выбранной своей компании.');
            },
        ];
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    protected function baseRules(): array
    {
        return [
            'intake_draft_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['draft', 'pending', 'confirmed', 'new', 'in_progress', 'documents', 'payment', 'closed', 'completed', 'cancelled', 'disruption'])],
            'own_company_id' => $this->ownCompanyIdRules(),
            'own_company_bank_account_id' => ['nullable', 'string', 'max:100'],
            'client_id' => ['required', 'integer', 'exists:contractors,id'],
            'order_date' => ['required', 'date'],
            'order_owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'dispatcher_id' => ['nullable', 'integer', 'exists:users,id'],
            'compensation_owner_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'compensation_dispatcher_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'special_notes' => ['nullable', 'string'],
            'customer_basic_terms' => ['nullable', 'array'],
            'customer_basic_terms.*' => ['nullable', 'string', 'max:8000'],
            'carrier_basic_terms' => ['nullable', 'array'],
            'carrier_basic_terms.*' => ['nullable', 'string', 'max:8000'],
            'svh_name' => ['nullable', 'string', 'max:500'],
            'svh_address' => ['nullable', 'string', 'max:500'],
            'customs_post_code' => ['nullable', 'string', 'max:120'],
            'cargo_declared_sum' => ['nullable', 'numeric', 'min:0'],
            'is_international_transport' => ['sometimes', 'boolean'],
            'loading_types' => ['nullable', 'array'],
            'loading_types.*' => ['nullable', Rule::in(['top', 'side', 'rear', 'full', 'tail_lift', 'crane'])],

            'additional_expenses' => ['nullable', 'numeric', 'min:0'],
            'additional_expenses_payment_date' => ['nullable', 'date'],
            'insurance' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],

            'performers' => ['nullable', 'array'],
            'performers.*.stage' => ['nullable', 'string', 'max:50'],
            'performers.*.carrier_mode' => ['nullable', 'string', Rule::in(['single', 'split'])],
            'performers.*.contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'performers.*.fleet_vehicle_id' => ['nullable', 'integer'],
            'performers.*.fleet_driver_id' => ['nullable', 'integer'],
            'performers.*.execution_mode' => ['nullable', 'string', Rule::in([OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET])],
            'performers.*.fleet_trip_id' => ['nullable', 'integer'],
            'performers.*.loading_actual' => ['nullable', 'date', 'before_or_equal:today'],
            'performers.*.unloading_actual' => ['nullable', 'date', 'before_or_equal:today'],
            'performers.*.loading_special_conditions' => ['nullable', 'string', 'max:2000'],
            'performers.*.unloading_special_conditions' => ['nullable', 'string', 'max:2000'],
            'performers.*.split_carriers' => ['nullable', 'array', 'max:4'],
            'performers.*.split_carriers.*.slot' => ['nullable', 'integer', 'min:1', 'max:9'],
            'performers.*.split_carriers.*.contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'performers.*.split_carriers.*.fleet_vehicle_id' => ['nullable', 'integer'],
            'performers.*.split_carriers.*.fleet_driver_id' => ['nullable', 'integer'],
            'performers.*.split_carriers.*.execution_mode' => ['nullable', 'string', Rule::in([OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET])],
            'performers.*.split_carriers.*.fleet_trip_id' => ['nullable', 'integer'],
            'performers.*.split_carriers.*.loading_actual' => ['nullable', 'date', 'before_or_equal:today'],
            'performers.*.split_carriers.*.unloading_actual' => ['nullable', 'date', 'before_or_equal:today'],

            'print_form_template_selection' => ['nullable', 'array'],
            'print_form_template_selection.*' => ['nullable', 'integer', 'exists:print_form_templates,id'],

            'route_points' => ['nullable', 'array'],
            'route_points.*.type' => ['nullable', Rule::in(['loading', 'unloading', 'border_crossing'])],
            'route_points.*.stage' => ['nullable', 'string', 'max:50'],
            'route_points.*.sequence' => ['nullable', 'integer', 'min:1'],
            'route_points.*.address' => ['nullable', 'string', 'max:500'],
            'route_points.*.normalized_data' => ['nullable', 'array'],
            'route_points.*.planned_date' => ['nullable', 'date'],
            'route_points.*.planned_time_from' => ['nullable', 'date_format:H:i'],
            'route_points.*.planned_time_to' => ['nullable', 'date_format:H:i'],
            'route_points.*.actual_date' => ['nullable', 'date', 'before_or_equal:today'],
            'route_points.*.actual_time' => ['nullable', 'date_format:H:i'],
            'route_points.*.contact_person' => ['nullable', 'string', 'max:255'],
            'route_points.*.contact_phone' => ['nullable', 'string', 'max:50'],
            'route_points.*.sender_name' => ['nullable', 'string', 'max:255'],
            'route_points.*.sender_contact' => ['nullable', 'string', 'max:255'],
            'route_points.*.sender_phone' => ['nullable', 'string', 'max:50'],
            'route_points.*.recipient_name' => ['nullable', 'string', 'max:255'],
            'route_points.*.recipient_contact' => ['nullable', 'string', 'max:255'],
            'route_points.*.recipient_phone' => ['nullable', 'string', 'max:50'],

            'cargo_items' => ['nullable', 'array'],
            'cargo_items.*.name' => ['nullable', 'string', 'max:500'],
            'cargo_items.*.description' => ['nullable', 'string'],
            'cargo_items.*.weight_value' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.weight_unit' => ['nullable', Rule::in(['kg', 't'])],
            'cargo_items.*.length_m' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.width_m' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.height_m' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.diameter_m' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.volume_m3' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.package_type' => ['nullable', Rule::in(['pallet', 'box', 'crate', 'roll', 'bag', 'barrel'])],
            'cargo_items.*.pack_type_id' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.pack_type_label' => ['nullable', 'string', 'max:255'],
            'cargo_items.*.loading_type_id' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.loading_type_code' => ['nullable', 'string', 'max:120'],
            'cargo_items.*.loading_type_label' => ['nullable', 'string', 'max:255'],
            'cargo_items.*.loading_type_items' => ['nullable', 'array'],
            'cargo_items.*.loading_type_items.*.id' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.loading_type_items.*.code' => ['nullable', 'string', 'max:120'],
            'cargo_items.*.loading_type_items.*.label' => ['nullable', 'string', 'max:255'],
            'cargo_items.*.truck_body_type_id' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.truck_body_type_code' => ['nullable', 'string', 'max:120'],
            'cargo_items.*.truck_body_type_label' => ['nullable', 'string', 'max:255'],
            'cargo_items.*.truck_body_type_items' => ['nullable', 'array'],
            'cargo_items.*.truck_body_type_items.*.id' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.truck_body_type_items.*.code' => ['nullable', 'string', 'max:120'],
            'cargo_items.*.truck_body_type_items.*.label' => ['nullable', 'string', 'max:255'],
            'cargo_items.*.trailer_type_id' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.trailer_type_code' => ['nullable', 'string', 'max:120'],
            'cargo_items.*.trailer_type_label' => ['nullable', 'string', 'max:255'],
            'cargo_items.*.trailer_type_items' => ['nullable', 'array'],
            'cargo_items.*.trailer_type_items.*.id' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.trailer_type_items.*.code' => ['nullable', 'string', 'max:120'],
            'cargo_items.*.trailer_type_items.*.label' => ['nullable', 'string', 'max:255'],
            'cargo_items.*.package_count' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.dangerous_goods' => ['nullable', 'boolean'],
            'cargo_items.*.dangerous_class' => ['nullable', 'string', 'max:10'],
            'cargo_items.*.hs_code' => ['nullable', 'string', 'max:50'],
            'cargo_items.*.cargo_type' => ['nullable', Rule::in(['general', 'dangerous', 'temperature_controlled', 'oversized', 'fragile'])],
            'cargo_items.*.cargo_type_id' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.cargo_type_label' => ['nullable', 'string', 'max:255'],
            'cargo_items.*.is_oversized' => ['nullable', 'boolean'],
            'cargo_items.*.is_fragile' => ['nullable', 'boolean'],
            'cargo_items.*.performer_allocations' => ['nullable', 'array'],
            'cargo_items.*.performer_allocations.*.stage' => ['nullable', 'string', 'max:50'],
            'cargo_items.*.performer_allocations.*.carrier_slot' => ['nullable', 'integer', 'min:1', 'max:9'],
            'cargo_items.*.performer_allocations.*.package_count' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.performer_allocations.*.weight_value' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.ati_cargo_payload' => ['nullable', 'array'],

            'financial_term' => ['nullable', 'array'],
            'financial_term.client_price' => ['nullable', 'numeric', 'min:0'],
            'financial_term.client_currency' => ['required_with:financial_term', Rule::in(CurrencyDictionary::allowedCodes())],
            'financial_term.client_payment_form' => ['nullable', Rule::in(PaymentFormDictionary::allowedCodesForValidation())],
            'financial_term.client_request_mode' => ['nullable', Rule::in(['single_request', 'split_by_leg'])],
            'financial_term.client_payment_terms' => ['nullable', 'string', 'max:400'],
            'financial_term.client_payment_schedule' => ['nullable', 'array'],
            'financial_term.client_payment_schedule.has_prepayment' => ['nullable', 'boolean'],
            'financial_term.client_payment_schedule.prepayment_ratio' => ['nullable', 'numeric', 'min:1', 'max:99'],
            'financial_term.client_payment_schedule.prepayment_days' => ['nullable', 'integer', 'min:0'],
            'financial_term.client_payment_schedule.prepayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.client_payment_schedule.postpayment_days' => ['nullable', 'integer', 'min:0'],
            'financial_term.client_payment_schedule.postpayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.client_payment_schedule.installments' => ['nullable', 'array', 'max:2'],
            'financial_term.client_payment_schedule.installments.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'financial_term.client_payment_schedule.installments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.client_payment_schedule.installments.*.offset_days' => ['nullable', 'integer', 'min:-730', 'max:730'],
            'financial_term.client_payment_schedule.installments.*.offset_unit' => ['nullable', Rule::in(['calendar_days', 'bank_days'])],
            'financial_term.client_payment_schedule.installments.*.anchor' => ['nullable', Rule::in(['first_loading', 'last_unloading', 'border_crossing', 'order_date', 'loading_date', 'unloading_date'])],
            'financial_term.client_payment_schedule.installments.*.basis' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.kpi_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'financial_term.contractors_costs' => ['nullable', 'array'],
            'financial_term.contractors_costs.*.stage' => ['nullable', 'string', 'max:50'],
            'financial_term.contractors_costs.*.carrier_slot' => ['nullable', 'integer', 'min:1', 'max:9'],
            'financial_term.contractors_costs.*.contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'financial_term.contractors_costs.*.incurred_date' => ['nullable', 'date'],
            'financial_term.contractors_costs.*.is_additional' => ['nullable', 'boolean'],
            'financial_term.contractors_costs.*.execution_mode' => ['nullable', 'string', Rule::in([OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET])],
            'financial_term.contractors_costs.*.amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.contractors_costs.*.currency' => ['nullable', Rule::in(CurrencyDictionary::allowedCodes())],
            'financial_term.contractors_costs.*.payment_form' => ['nullable', Rule::in(PaymentFormDictionary::allowedCodesForValidation())],
            'financial_term.contractors_costs.*.payment_terms' => ['nullable', 'string', 'max:400'],
            'financial_term.contractors_costs.*.payment_schedule' => ['nullable', 'array'],
            'financial_term.contractors_costs.*.payment_schedule.has_prepayment' => ['nullable', 'boolean'],
            'financial_term.contractors_costs.*.payment_schedule.prepayment_ratio' => ['nullable', 'numeric', 'min:1', 'max:99'],
            'financial_term.contractors_costs.*.payment_schedule.prepayment_days' => ['nullable', 'integer', 'min:0'],
            'financial_term.contractors_costs.*.payment_schedule.prepayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.contractors_costs.*.payment_schedule.postpayment_days' => ['nullable', 'integer', 'min:0'],
            'financial_term.contractors_costs.*.payment_schedule.postpayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.contractors_costs.*.payment_schedule.installments' => ['nullable', 'array', 'max:2'],
            'financial_term.contractors_costs.*.payment_schedule.installments.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'financial_term.contractors_costs.*.payment_schedule.installments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.contractors_costs.*.payment_schedule.installments.*.offset_days' => ['nullable', 'integer', 'min:-730', 'max:730'],
            'financial_term.contractors_costs.*.payment_schedule.installments.*.offset_unit' => ['nullable', Rule::in(['calendar_days', 'bank_days'])],
            'financial_term.contractors_costs.*.payment_schedule.installments.*.anchor' => ['nullable', Rule::in(['first_loading', 'last_unloading', 'border_crossing', 'order_date', 'loading_date', 'unloading_date'])],
            'financial_term.contractors_costs.*.payment_schedule.installments.*.basis' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.additional_costs' => ['nullable', 'array'],
            'financial_term.additional_costs.*.id' => ['nullable', 'string', 'max:64'],
            'financial_term.additional_costs.*.contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'financial_term.additional_costs.*.contractor_name' => ['nullable', 'string', 'max:500'],
            'financial_term.additional_costs.*.service_date' => ['nullable', 'date'],
            'financial_term.additional_costs.*.amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.additional_costs.*.currency' => ['nullable', Rule::in(CurrencyDictionary::allowedCodes())],
            'financial_term.additional_costs.*.payment_form' => ['nullable', 'string', 'max:50', Rule::in(PaymentFormDictionary::allowedCodesForValidation())],
            'financial_term.additional_costs.*.payment_terms' => ['nullable', 'string', 'max:2000'],
            'financial_term.additional_costs.*.payment_schedule' => ['nullable', 'array'],
            'financial_term.additional_costs.*.payment_schedule.has_prepayment' => ['nullable', 'boolean'],
            'financial_term.additional_costs.*.payment_schedule.prepayment_ratio' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'financial_term.additional_costs.*.payment_schedule.prepayment_days' => ['nullable', 'integer', 'min:0'],
            'financial_term.additional_costs.*.payment_schedule.prepayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.additional_costs.*.payment_schedule.postpayment_days' => ['nullable', 'integer', 'min:0'],
            'financial_term.additional_costs.*.payment_schedule.postpayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.additional_costs.*.payment_schedule.installments' => ['nullable', 'array', 'max:2'],
            'financial_term.additional_costs.*.payment_schedule.installments.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'financial_term.additional_costs.*.payment_schedule.installments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.additional_costs.*.payment_schedule.installments.*.offset_days' => ['nullable', 'integer', 'min:-730', 'max:730'],
            'financial_term.additional_costs.*.payment_schedule.installments.*.offset_unit' => ['nullable', Rule::in(['calendar_days', 'bank_days'])],
            'financial_term.additional_costs.*.payment_schedule.installments.*.anchor' => ['nullable', Rule::in(['first_loading', 'last_unloading', 'border_crossing', 'order_date', 'loading_date', 'unloading_date'])],
            'financial_term.additional_costs.*.payment_schedule.installments.*.basis' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.additional_costs.*.label' => ['nullable', 'string', 'max:100'],

            'financial_term.client_norms_penalties' => ['nullable', 'array'],
            'financial_term.client_norms_penalties.miss_amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.client_norms_penalties.miss_currency' => ['nullable', Rule::in(CurrencyDictionary::allowedCodes())],
            'financial_term.client_norms_penalties.downtime_amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.client_norms_penalties.downtime_currency' => ['nullable', Rule::in(CurrencyDictionary::allowedCodes())],
            'financial_term.client_norms_penalties.fine_amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.client_norms_penalties.fine_currency' => ['nullable', Rule::in(CurrencyDictionary::allowedCodes())],
            'financial_term.client_norms_penalties.penalty_terms' => ['nullable', 'string', 'max:2000'],
            'financial_term.client_norms_penalties.norm_loading_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'financial_term.client_norms_penalties.norm_customs_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'financial_term.client_norms_penalties.norm_unloading_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],

            'financial_term.carrier_norms_by_leg' => ['nullable', 'array'],
            'financial_term.carrier_norms_by_leg.*.stage' => ['nullable', 'string', 'max:50'],
            'financial_term.carrier_norms_by_leg.*.miss_amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.carrier_norms_by_leg.*.miss_currency' => ['nullable', Rule::in(CurrencyDictionary::allowedCodes())],
            'financial_term.carrier_norms_by_leg.*.downtime_amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.carrier_norms_by_leg.*.downtime_currency' => ['nullable', Rule::in(CurrencyDictionary::allowedCodes())],
            'financial_term.carrier_norms_by_leg.*.fine_amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.carrier_norms_by_leg.*.fine_currency' => ['nullable', Rule::in(CurrencyDictionary::allowedCodes())],
            'financial_term.carrier_norms_by_leg.*.penalty_terms' => ['nullable', 'string', 'max:2000'],
            'financial_term.carrier_norms_by_leg.*.norm_loading_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'financial_term.carrier_norms_by_leg.*.norm_customs_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'financial_term.carrier_norms_by_leg.*.norm_unloading_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],

            'documents' => ['nullable', 'array'],
            'documents.*.id' => ['nullable', 'integer', 'min:1'],
            'documents.*.type' => ['required', Rule::in(OrderDocumentRegistryTypes::values())],
            'documents.*.flow' => ['nullable', Rule::in(['uploaded', 'generated', 'print_template_workflow'])],
            'documents.*.party' => ['required', Rule::in(['customer', 'carrier', 'contractor', 'internal'])],
            'documents.*.contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'documents.*.carrier_contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'documents.*.stage' => ['nullable', 'string', 'max:50'],
            'documents.*.requirement_key' => ['nullable', 'string', 'max:100'],
            'documents.*.number' => ['nullable', 'string', 'max:255'],
            'documents.*.document_date' => ['nullable', 'date'],
            'documents.*.status' => ['required', Rule::in(['draft', 'pending', 'signed', 'sent'])],
            'documents.*.template_id' => ['nullable', 'integer'],
            'documents.*.file' => [
                'nullable',
                'file',
                'max:'.DocumentUploadBudget::absoluteMaxKilobytes(),
                new DocumentWithinPageBudget,
            ],
        ];
    }

    /**
     * @return list<string|ValidationRule>
     */
    protected function ownCompanyIdRules(): array
    {
        $rules = ['nullable', 'integer'];

        if (! Schema::hasTable('contractors')) {
            return $rules;
        }

        if (Schema::hasColumn('contractors', 'is_own_company')) {
            $rules[] = Rule::exists('contractors', 'id')->where('is_own_company', true);
        } else {
            $rules[] = Rule::exists('contractors', 'id');
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'performers.*.loading_actual.before_or_equal' => 'Фактическая дата погрузки не может быть позже сегодняшнего дня.',
            'performers.*.unloading_actual.before_or_equal' => 'Фактическая дата выгрузки не может быть позже сегодняшнего дня.',
            'performers.*.split_carriers.*.loading_actual.before_or_equal' => 'Фактическая дата погрузки не может быть позже сегодняшнего дня.',
            'performers.*.split_carriers.*.unloading_actual.before_or_equal' => 'Фактическая дата выгрузки не может быть позже сегодняшнего дня.',
            'route_points.*.actual_date.before_or_equal' => 'Фактическая дата точки маршрута не может быть позже сегодняшнего дня.',
        ];
    }
}

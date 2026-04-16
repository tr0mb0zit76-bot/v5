<?php

namespace App\Http\Requests;

use App\Models\Contractor;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Services\ContractorCreditService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
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
        if (! $this->has('order_payload')) {
            return;
        }

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
                    ->map(fn (array $item): int => (int) ($item['contractor_id'] ?? 0));

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

                $clientPrice = Arr::get($this->input('financial_term', []), 'client_price');
                if ($clientPrice === null || $clientPrice === '' || (float) $clientPrice <= 0) {
                    $validator->errors()->add('financial_term.client_price', 'Укажите цену клиента больше 0.');
                }
            },
            function (Validator $validator): void {
                $documents = $this->input('documents', []);
                if (! is_array($documents)) {
                    return;
                }

                foreach ($documents as $index => $document) {
                    $file = $this->file("documents.$index.file");
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $type = is_array($document) ? (string) ($document['type'] ?? '') : '';
                    $maxKb = in_array($type, self::CONTRACT_TYPES, true) ? 3072 : 1024;
                    if ((int) $file->getSize() > ($maxKb * 1024)) {
                        $validator->errors()->add(
                            "documents.$index.file",
                            $maxKb === 3072
                                ? 'Для договоров допустим размер файла до 3 МБ.'
                                : 'Для этого типа документа допустим размер файла до 1 МБ.'
                        );
                    }
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

                    $carrierId = isset($performer['contractor_id']) ? (int) $performer['contractor_id'] : null;
                    $vehicleId = isset($performer['fleet_vehicle_id']) ? (int) $performer['fleet_vehicle_id'] : null;
                    $driverId = isset($performer['fleet_driver_id']) ? (int) $performer['fleet_driver_id'] : null;

                    if ($vehicleId > 0) {
                        $vehicle = FleetVehicle::query()->find($vehicleId);
                        if ($vehicle === null) {
                            $validator->errors()->add("performers.$i.fleet_vehicle_id", 'Транспортное средство не найдено.');
                        } elseif ($carrierId && (int) $vehicle->owner_contractor_id !== $carrierId) {
                            $validator->errors()->add("performers.$i.fleet_vehicle_id", 'ТС должно принадлежать выбранному перевозчику (владелец в карточке ТС).');
                        }
                    }

                    if ($driverId > 0) {
                        $driver = FleetDriver::query()->find($driverId);
                        if ($driver === null) {
                            $validator->errors()->add("performers.$i.fleet_driver_id", 'Водитель не найден.');
                        } elseif ($carrierId && (int) $driver->carrier_contractor_id !== $carrierId) {
                            $validator->errors()->add("performers.$i.fleet_driver_id", 'Водитель должен быть привязан к выбранному контрагенту-перевозчику.');
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    protected function baseRules(): array
    {
        return [
            'status' => ['required', Rule::in(['draft', 'pending', 'confirmed', 'new', 'in_progress', 'documents', 'payment', 'closed', 'completed', 'cancelled'])],
            'own_company_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'client_id' => ['required', 'integer', 'exists:contractors,id'],
            'order_date' => ['required', 'date'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'special_notes' => ['nullable', 'string'],
            'loading_types' => ['nullable', 'array'],
            'loading_types.*' => ['nullable', Rule::in(['top', 'side', 'rear'])],

            'additional_expenses' => ['nullable', 'numeric', 'min:0'],
            'insurance' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],

            'performers' => ['nullable', 'array'],
            'performers.*.stage' => ['nullable', 'string', 'max:50'],
            'performers.*.contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'performers.*.fleet_vehicle_id' => ['nullable', 'integer'],
            'performers.*.fleet_driver_id' => ['nullable', 'integer'],

            'route_points' => ['nullable', 'array'],
            'route_points.*.type' => ['nullable', Rule::in(['loading', 'unloading'])],
            'route_points.*.stage' => ['nullable', 'string', 'max:50'],
            'route_points.*.sequence' => ['nullable', 'integer', 'min:1'],
            'route_points.*.address' => ['nullable', 'string', 'max:500'],
            'route_points.*.normalized_data' => ['nullable', 'array'],
            'route_points.*.planned_date' => ['nullable', 'date'],
            'route_points.*.planned_time_from' => ['nullable', 'date_format:H:i'],
            'route_points.*.planned_time_to' => ['nullable', 'date_format:H:i'],
            'route_points.*.actual_date' => ['nullable', 'date'],
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
            'cargo_items.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.volume_m3' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.package_type' => ['nullable', Rule::in(['pallet', 'box', 'crate', 'roll', 'bag'])],
            'cargo_items.*.package_count' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.dangerous_goods' => ['nullable', 'boolean'],
            'cargo_items.*.dangerous_class' => ['nullable', 'string', 'max:10'],
            'cargo_items.*.hs_code' => ['nullable', 'string', 'max:50'],
            'cargo_items.*.cargo_type' => ['nullable', Rule::in(['general', 'dangerous', 'temperature_controlled', 'oversized', 'fragile'])],

            'financial_term' => ['nullable', 'array'],
            'financial_term.client_price' => ['nullable', 'numeric', 'min:0'],
            'financial_term.client_currency' => ['required_with:financial_term', Rule::in(['RUB', 'USD', 'CNY', 'EUR'])],
            'financial_term.client_payment_form' => ['nullable', Rule::in(['vat', 'no_vat', 'cash'])],
            'financial_term.client_request_mode' => ['nullable', Rule::in(['single_request', 'split_by_leg'])],
            'financial_term.client_payment_schedule' => ['nullable', 'array'],
            'financial_term.client_payment_schedule.has_prepayment' => ['nullable', 'boolean'],
            'financial_term.client_payment_schedule.prepayment_ratio' => ['nullable', 'numeric', 'min:1', 'max:99'],
            'financial_term.client_payment_schedule.prepayment_days' => ['nullable', 'integer', 'min:0'],
            'financial_term.client_payment_schedule.prepayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.client_payment_schedule.postpayment_days' => ['nullable', 'integer', 'min:0'],
            'financial_term.client_payment_schedule.postpayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.kpi_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'financial_term.contractors_costs' => ['nullable', 'array'],
            'financial_term.contractors_costs.*.stage' => ['nullable', 'string', 'max:50'],
            'financial_term.contractors_costs.*.contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'financial_term.contractors_costs.*.amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.contractors_costs.*.currency' => ['nullable', Rule::in(['RUB', 'USD', 'CNY', 'EUR'])],
            'financial_term.contractors_costs.*.payment_form' => ['nullable', Rule::in(['vat', 'no_vat', 'cash'])],
            'financial_term.contractors_costs.*.payment_schedule' => ['nullable', 'array'],
            'financial_term.contractors_costs.*.payment_schedule.has_prepayment' => ['nullable', 'boolean'],
            'financial_term.contractors_costs.*.payment_schedule.prepayment_ratio' => ['nullable', 'numeric', 'min:1', 'max:99'],
            'financial_term.contractors_costs.*.payment_schedule.prepayment_days' => ['nullable', 'integer', 'min:0'],
            'financial_term.contractors_costs.*.payment_schedule.prepayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.contractors_costs.*.payment_schedule.postpayment_days' => ['nullable', 'integer', 'min:0'],
            'financial_term.contractors_costs.*.payment_schedule.postpayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'])],
            'financial_term.additional_costs' => ['nullable', 'array'],
            'financial_term.additional_costs.*.label' => ['nullable', 'string', 'max:100'],
            'financial_term.additional_costs.*.amount' => ['nullable', 'numeric', 'min:0'],
            'financial_term.additional_costs.*.currency' => ['nullable', Rule::in(['RUB', 'USD', 'CNY', 'EUR'])],

            'documents' => ['nullable', 'array'],
            'documents.*.type' => ['required', Rule::in(['request', 'contract', 'contract_request', 'waybill', 'cmr', 'upd', 'invoice', 'invoice_factura', 'act', 'packing_list', 'customs_declaration', 'other'])],
            'documents.*.flow' => ['nullable', Rule::in(['uploaded', 'generated', 'print_template_workflow'])],
            'documents.*.party' => ['required', Rule::in(['customer', 'carrier', 'internal'])],
            'documents.*.stage' => ['nullable', 'string', 'max:50'],
            'documents.*.requirement_key' => ['nullable', 'string', 'max:100'],
            'documents.*.number' => ['nullable', 'string', 'max:255'],
            'documents.*.document_date' => ['nullable', 'date'],
            'documents.*.status' => ['required', Rule::in(['draft', 'pending', 'signed', 'sent'])],
            'documents.*.template_id' => ['nullable', 'integer'],
            'documents.*.file' => ['nullable', 'file', 'max:3072'],
        ];
    }
}

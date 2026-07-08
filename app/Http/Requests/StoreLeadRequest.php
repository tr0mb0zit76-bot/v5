<?php

namespace App\Http\Requests;

use App\Enums\LeadCloseOutcomeFlag;
use App\Support\CurrencyDictionary;
use App\Support\PaymentFormDictionary;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeadRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user === null) {
            return;
        }

        if (! $this->filled('responsible_id')) {
            $this->merge(['responsible_id' => $user->id]);
        }

        if ($this->has('title')) {
            $this->merge(['title' => trim((string) $this->input('title'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return $this->baseRules();
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    protected function baseRules(): array
    {
        return [
            'status' => ['required', Rule::in(['new', 'qualification', 'calculation', 'proposal_ready', 'proposal_sent', 'negotiation', 'won', 'lost', 'on_hold'])],
            'source' => ['nullable', 'string', 'max:100'],
            'counterparty_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'responsible_id' => ['required', 'integer', 'exists:users,id'],
            'title' => $this->leadTitleRules(),
            'description' => ['nullable', 'string'],
            'transport_type' => ['nullable', 'string', 'max:100'],
            'loading_location' => ['nullable', 'string', 'max:255'],
            'unloading_location' => ['nullable', 'string', 'max:255'],
            'planned_shipping_date' => ['nullable', 'date'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'target_currency' => ['required_with:target_price', Rule::in(CurrencyDictionary::allowedCodes())],
            'customer_payment_form' => ['nullable', 'string', Rule::in(PaymentFormDictionary::allowedCodesForValidation())],
            'carrier_payment_form' => ['nullable', 'string', Rule::in(PaymentFormDictionary::allowedCodesForValidation())],
            'calculated_cost' => ['nullable', 'numeric', 'min:0'],
            'expected_margin' => ['nullable', 'numeric'],
            'next_contact_at' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'close_outcome_primary_flag' => ['nullable', 'string', Rule::enum(LeadCloseOutcomeFlag::class)],
            'close_outcome_note' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'array'],
            'qualification.need' => ['nullable', 'string', 'max:255'],
            'qualification.timeline' => ['nullable', 'string', 'max:255'],
            'qualification.authority' => ['nullable', 'string', 'max:255'],
            'qualification.budget' => ['nullable', 'string', 'max:255'],

            'business_process_id' => Schema::hasColumn('leads', 'business_process_id')
                ? ['required', 'integer', 'exists:business_processes,id']
                : ['nullable'],

            'route_points' => ['nullable', 'array'],
            'route_points.*.type' => ['nullable', Rule::in(['loading', 'unloading', 'border_crossing'])],
            'route_points.*.stage' => ['nullable', 'string', 'max:50'],
            'route_points.*.sequence' => ['nullable', 'integer', 'min:1'],
            'route_points.*.address' => ['nullable', 'string', 'max:500'],
            'route_points.*.normalized_data' => ['nullable', 'array'],
            'route_points.*.planned_date' => ['nullable', 'date'],
            'route_points.*.planned_time_from' => ['nullable', 'date_format:H:i'],
            'route_points.*.planned_time_to' => ['nullable', 'date_format:H:i'],
            'route_points.*.contact_person' => ['nullable', 'string', 'max:255'],
            'route_points.*.contact_phone' => ['nullable', 'string', 'max:50'],
            'route_points.*.sender_name' => ['nullable', 'string', 'max:255'],
            'route_points.*.sender_contact' => ['nullable', 'string', 'max:255'],
            'route_points.*.sender_phone' => ['nullable', 'string', 'max:50'],
            'route_points.*.recipient_name' => ['nullable', 'string', 'max:255'],
            'route_points.*.recipient_contact' => ['nullable', 'string', 'max:255'],
            'route_points.*.recipient_phone' => ['nullable', 'string', 'max:50'],

            'performers' => ['nullable', 'array'],
            'performers.*.stage' => ['nullable', 'string', 'max:50'],
            'performers.*.contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'performers.*.contractor_name' => ['nullable', 'string', 'max:255'],
            'performers.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],

            'precalculation' => ['nullable', 'array'],
            'precalculation.status' => ['nullable', Rule::in(['draft', 'ready', 'archived'])],
            'precalculation.freight' => ['nullable', 'array'],
            'precalculation.freight.to_border_total' => ['nullable', 'numeric', 'min:0'],
            'precalculation.freight.after_border_total' => ['nullable', 'numeric', 'min:0'],
            'precalculation.freight.distribution_basis' => ['nullable', Rule::in(['invoice_rub', 'weight_kg', 'equal'])],
            'precalculation.goods_lines' => ['nullable', 'array'],
            'precalculation.goods_lines.*.id' => ['nullable', 'string', 'max:64'],
            'precalculation.goods_lines.*.description' => ['nullable', 'string', 'max:255'],
            'precalculation.goods_lines.*.tn_ved_code' => ['nullable', 'string', 'max:20'],
            'precalculation.goods_lines.*.tn_ved_label' => ['nullable', 'string', 'max:255'],
            'precalculation.goods_lines.*.quantity' => ['nullable', 'integer', 'min:1'],
            'precalculation.goods_lines.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'precalculation.goods_lines.*.invoice_amount' => ['nullable', 'numeric', 'min:0'],
            'precalculation.goods_lines.*.currency' => ['nullable', 'string', 'max:8'],
            'precalculation.goods_lines.*.exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'precalculation.goods_lines.*.freight_to_border' => ['nullable', 'numeric', 'min:0'],
            'precalculation.goods_lines.*.freight_after_border' => ['nullable', 'numeric', 'min:0'],
            'precalculation.goods_lines.*.other_costs' => ['nullable', 'numeric', 'min:0'],
            'precalculation.goods_lines.*.vehicle_age_years' => ['nullable', 'integer', 'min:0', 'max:50'],
            'precalculation.goods_lines.*.include_utilization_fee' => ['nullable', 'boolean'],
            'precalculation.service_lines' => ['nullable', 'array'],
            'precalculation.service_lines.*.kind' => ['nullable', Rule::in(['logistics', 'other'])],
            'precalculation.service_lines.*.title' => ['nullable', 'string', 'max:255'],
            'precalculation.service_lines.*.stage' => ['nullable', 'string', 'max:50'],
            'precalculation.service_lines.*.amount' => ['nullable', 'numeric', 'min:0'],
            'precalculation.service_lines.*.currency' => ['nullable', 'string', 'max:8'],

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
            'cargo_items.*.loading_type_ids' => ['nullable', 'array'],
            'cargo_items.*.truck_body_type_id' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.truck_body_type_code' => ['nullable', 'string', 'max:120'],
            'cargo_items.*.truck_body_type_label' => ['nullable', 'string', 'max:255'],
            'cargo_items.*.truck_body_type_items' => ['nullable', 'array'],
            'cargo_items.*.truck_body_type_ids' => ['nullable', 'array'],
            'cargo_items.*.trailer_type_id' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.trailer_type_code' => ['nullable', 'string', 'max:120'],
            'cargo_items.*.trailer_type_label' => ['nullable', 'string', 'max:255'],
            'cargo_items.*.trailer_type_items' => ['nullable', 'array'],
            'cargo_items.*.trailer_type_ids' => ['nullable', 'array'],
            'cargo_items.*.package_count' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.dangerous_goods' => ['nullable', 'boolean'],
            'cargo_items.*.dangerous_class' => ['nullable', 'string', 'max:10'],
            'cargo_items.*.hs_code' => ['nullable', 'string', 'max:50'],
            'cargo_items.*.cargo_type_id' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.cargo_type_label' => ['nullable', 'string', 'max:255'],
            'cargo_items.*.cargo_type' => ['nullable', Rule::in(['general', 'dangerous', 'temperature_controlled', 'oversized', 'fragile'])],
            'cargo_items.*.is_oversized' => ['nullable', 'boolean'],
            'cargo_items.*.is_fragile' => ['nullable', 'boolean'],

            'link_task_id' => Schema::hasTable('tasks')
                ? ['nullable', 'integer', 'exists:tasks,id']
                : ['nullable'],

            'activities' => ['nullable', 'array'],
            'activities.*.type' => ['required', Rule::in(['call', 'email', 'meeting', 'note', 'status_change'])],
            'activities.*.subject' => ['nullable', 'string', 'max:255'],
            'activities.*.content' => ['nullable', 'string'],
            'activities.*.next_action_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return list<string|ValidationRule>
     */
    protected function leadTitleRules(): array
    {
        $rules = ['required', 'string', 'max:255'];

        if (! Schema::hasTable('leads')) {
            return $rules;
        }

        $unique = Rule::unique('leads', 'title');
        if (Schema::hasColumn('leads', 'deleted_at')) {
            $unique = $unique->whereNull('deleted_at');
        }
        $rules[] = $unique;

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = (string) $this->input('status', '');
            $flagValue = $this->input('close_outcome_primary_flag');
            $note = $this->input('close_outcome_note');

            if ($status === 'lost' && blank($flagValue)) {
                $validator->errors()->add('close_outcome_primary_flag', 'Укажите причину проигрыша.');
            }

            if ($flagValue === null || $flagValue === '') {
                return;
            }

            $flag = LeadCloseOutcomeFlag::tryFrom((string) $flagValue);

            if ($flag === null) {
                $validator->errors()->add('close_outcome_primary_flag', 'Недопустимая причина закрытия.');

                return;
            }

            if ($status === 'lost' && $flag->terminalOutcome() !== 'lost') {
                $validator->errors()->add('close_outcome_primary_flag', 'Для проигрыша выберите причину из списка отказов.');
            }

            if ($status === 'won' && $flag->terminalOutcome() !== 'won') {
                $validator->errors()->add('close_outcome_primary_flag', 'Для выигрыша выберите причину из списка успехов.');
            }

            if ($flag === LeadCloseOutcomeFlag::LostOther && blank($note) && blank($this->input('lost_reason'))) {
                $validator->errors()->add('close_outcome_note', 'Кратко уточните причину «Другое».');
            }
        });
    }
}

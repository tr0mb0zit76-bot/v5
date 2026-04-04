<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'transport_type' => ['nullable', 'string', 'max:100'],
            'loading_location' => ['nullable', 'string', 'max:255'],
            'unloading_location' => ['nullable', 'string', 'max:255'],
            'planned_shipping_date' => ['nullable', 'date'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'target_currency' => ['required_with:target_price', Rule::in(['RUB', 'USD', 'CNY', 'EUR'])],
            'calculated_cost' => ['nullable', 'numeric', 'min:0'],
            'expected_margin' => ['nullable', 'numeric'],
            'next_contact_at' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'array'],
            'qualification.need' => ['nullable', 'string', 'max:255'],
            'qualification.timeline' => ['nullable', 'string', 'max:255'],
            'qualification.authority' => ['nullable', 'string', 'max:255'],
            'qualification.budget' => ['nullable', 'string', 'max:255'],

            'route_points' => ['nullable', 'array'],
            'route_points.*.type' => ['required', Rule::in(['loading', 'unloading'])],
            'route_points.*.sequence' => ['nullable', 'integer', 'min:1'],
            'route_points.*.address' => ['required', 'string', 'max:500'],
            'route_points.*.normalized_data' => ['nullable', 'array'],
            'route_points.*.planned_date' => ['nullable', 'date'],
            'route_points.*.contact_person' => ['nullable', 'string', 'max:255'],
            'route_points.*.contact_phone' => ['nullable', 'string', 'max:50'],

            'cargo_items' => ['nullable', 'array'],
            'cargo_items.*.name' => ['required', 'string', 'max:255'],
            'cargo_items.*.description' => ['nullable', 'string'],
            'cargo_items.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.volume_m3' => ['nullable', 'numeric', 'min:0'],
            'cargo_items.*.package_type' => ['nullable', Rule::in(['pallet', 'box', 'crate', 'roll', 'bag'])],
            'cargo_items.*.package_count' => ['nullable', 'integer', 'min:0'],
            'cargo_items.*.dangerous_goods' => ['required', 'boolean'],
            'cargo_items.*.dangerous_class' => ['nullable', 'string', 'max:10'],
            'cargo_items.*.hs_code' => ['nullable', 'string', 'max:50'],
            'cargo_items.*.cargo_type' => ['required', Rule::in(['general', 'dangerous', 'temperature_controlled', 'oversized', 'fragile'])],

            'activities' => ['nullable', 'array'],
            'activities.*.type' => ['required', Rule::in(['call', 'email', 'meeting', 'note', 'status_change'])],
            'activities.*.subject' => ['nullable', 'string', 'max:255'],
            'activities.*.content' => ['nullable', 'string'],
            'activities.*.next_action_at' => ['nullable', 'date'],
        ];
    }
}

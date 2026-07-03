<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoadBoardPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'customer_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'priority' => ['required', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'title' => ['required', 'string', 'max:255'],
            'loading_location' => ['nullable', 'string', 'max:255'],
            'unloading_location' => ['nullable', 'string', 'max:255'],
            'loading_date' => ['nullable', 'date'],
            'unloading_date' => ['nullable', 'date', 'after_or_equal:loading_date'],
            'cargo_name' => ['nullable', 'string', 'max:255'],
            'ati_cargo_name' => ['nullable', 'string', 'max:500'],
            'cargo_weight' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'cargo_volume' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'cargo_type_id' => ['nullable', 'integer', 'min:0'],
            'cargo_type' => ['nullable', 'string', 'max:120'],
            'cargo_type_label' => ['nullable', 'string', 'max:255'],
            'pack_type_id' => ['nullable', 'integer', 'min:0'],
            'package_type' => ['nullable', 'string', 'max:120'],
            'pack_type_label' => ['nullable', 'string', 'max:255'],
            'package_count' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'loading_type_id' => ['nullable', 'integer', 'min:0'],
            'loading_type_code' => ['nullable', 'string', 'max:120'],
            'loading_type_label' => ['nullable', 'string', 'max:255'],
            'loading_type_items' => ['nullable', 'array'],
            'loading_type_items.*.id' => ['nullable', 'integer', 'min:0'],
            'loading_type_items.*.code' => ['nullable', 'string', 'max:120'],
            'loading_type_items.*.label' => ['nullable', 'string', 'max:255'],
            'truck_body_type_id' => ['nullable', 'integer', 'min:0'],
            'truck_body_type_code' => ['nullable', 'string', 'max:120'],
            'truck_body_type_label' => ['nullable', 'string', 'max:255'],
            'truck_body_type_items' => ['nullable', 'array'],
            'truck_body_type_items.*.id' => ['nullable', 'integer', 'min:0'],
            'truck_body_type_items.*.code' => ['nullable', 'string', 'max:120'],
            'truck_body_type_items.*.label' => ['nullable', 'string', 'max:255'],
            'trailer_type_id' => ['nullable', 'integer', 'min:0'],
            'trailer_type_code' => ['nullable', 'string', 'max:120'],
            'trailer_type_label' => ['nullable', 'string', 'max:255'],
            'trailer_type_items' => ['nullable', 'array'],
            'trailer_type_items.*.id' => ['nullable', 'integer', 'min:0'],
            'trailer_type_items.*.code' => ['nullable', 'string', 'max:120'],
            'trailer_type_items.*.label' => ['nullable', 'string', 'max:255'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'diameter' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'is_hazardous' => ['nullable', 'boolean'],
            'hazard_class' => ['nullable', 'string', 'max:50'],
            'needs_temperature' => ['nullable', 'boolean'],
            'temp_min' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'temp_max' => ['nullable', 'numeric', 'min:-100', 'max:100', 'gte:temp_min'],
            'is_oversized' => ['nullable', 'boolean'],
            'is_fragile' => ['nullable', 'boolean'],
            'hs_code' => ['nullable', 'string', 'max:32'],
            'ati_cargo_payload' => ['nullable', 'array'],
            'transport_type' => ['nullable', 'string', 'max:255'],
            'customer_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'customer_rate_currency' => ['nullable', 'string', 'size:3'],
            'target_carrier_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'payment_form' => ['nullable', 'string', 'max:255'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'seller_comment' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

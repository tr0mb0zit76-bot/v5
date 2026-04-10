<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesScriptPlaySessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sales_script_version_id' => ['required', 'integer', 'exists:sales_script_versions,id'],
            'contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ];
    }
}

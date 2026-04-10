<?php

namespace App\Http\Requests\SalesScripts;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransitionRequest extends FormRequest
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
            'from_node_id' => ['required', 'integer', 'exists:sales_script_nodes,id'],
            'to_node_id' => ['required', 'integer', 'exists:sales_script_nodes,id'],
            'sales_script_reaction_class_id' => ['nullable', 'integer', 'exists:sales_script_reaction_classes,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

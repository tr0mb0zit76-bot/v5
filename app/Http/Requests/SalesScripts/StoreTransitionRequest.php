<?php

namespace App\Http\Requests\SalesScripts;

use App\Services\SalesScripts\SalesScriptConversationGuidanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'target_type' => ['nullable', 'string', Rule::in(['node', 'script', 'return'])],
            'target_sales_script_version_id' => ['nullable', 'integer', 'exists:sales_script_versions,id'],
            'sales_script_reaction_class_id' => ['nullable', 'integer', 'exists:sales_script_reaction_classes,id'],
            'customer_label' => ['nullable', 'string', 'max:500'],
            'conversation_effect' => ['nullable', 'string', Rule::in(SalesScriptConversationGuidanceService::effects())],
            'momentum_delta' => ['nullable', 'integer', 'min:-2', 'max:2'],
            'next_move_preview' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

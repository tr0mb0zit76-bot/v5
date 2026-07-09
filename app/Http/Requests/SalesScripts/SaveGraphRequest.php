<?php

namespace App\Http\Requests\SalesScripts;

use App\Enums\SalesScriptNodeKind;
use App\Services\SalesScripts\SalesScriptConversationGuidanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGraphRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'autosave' => ['sometimes', 'boolean'],
            'entry_node_key' => ['nullable', 'string', 'max:255'],
            'nodes' => ['required', 'array', 'min:1'],
            'nodes.*.client_key' => ['required', 'string', 'max:255'],
            'nodes.*.kind' => ['required', 'string', Rule::enum(SalesScriptNodeKind::class)],
            'nodes.*.body' => ['required', 'string'],
            'nodes.*.body_variant_b' => ['nullable', 'string'],
            'nodes.*.ab_enabled' => ['nullable', 'boolean'],
            'nodes.*.ab_variant_b_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'nodes.*.hint' => ['nullable', 'string'],
            'nodes.*.tags' => ['nullable', 'array'],
            'nodes.*.tags.*' => ['string', 'max:100'],
            'nodes.*.capture_field_codes' => ['nullable', 'array'],
            'nodes.*.capture_field_codes.*' => ['string', 'max:64'],
            'nodes.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'nodes.*.canvas_x' => ['nullable', 'integer', 'min:-100000', 'max:100000'],
            'nodes.*.canvas_y' => ['nullable', 'integer', 'min:-100000', 'max:100000'],
            'transitions' => ['nullable', 'array'],
            'transitions.*.from_client_key' => ['required', 'string', 'max:255'],
            'transitions.*.to_client_key' => ['required', 'string', 'max:255'],
            'transitions.*.target_type' => ['nullable', 'string', Rule::in(['node', 'script', 'return'])],
            'transitions.*.target_sales_script_version_id' => ['nullable', 'integer', 'exists:sales_script_versions,id'],
            'transitions.*.sales_script_reaction_class_id' => ['nullable', 'integer', 'exists:sales_script_reaction_classes,id'],
            'transitions.*.customer_label' => ['nullable', 'string', 'max:500'],
            'transitions.*.conversation_effect' => ['nullable', 'string', Rule::in(SalesScriptConversationGuidanceService::effects())],
            'transitions.*.momentum_delta' => ['nullable', 'integer', 'min:-2', 'max:2'],
            'transitions.*.next_move_preview' => ['nullable', 'string', 'max:500'],
            'transitions.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

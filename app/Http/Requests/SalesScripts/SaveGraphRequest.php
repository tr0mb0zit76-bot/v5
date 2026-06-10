<?php

namespace App\Http\Requests\SalesScripts;

use App\Enums\SalesScriptNodeKind;
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
            'transitions.*.sales_script_reaction_class_id' => ['nullable', 'integer', 'exists:sales_script_reaction_classes,id'],
            'transitions.*.customer_label' => ['nullable', 'string', 'max:500'],
            'transitions.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

<?php

namespace App\Http\Requests\SalesScripts;

use App\Enums\SalesScriptNodeKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNodeTemplateRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:160'],
            'kind' => ['required', 'string', Rule::enum(SalesScriptNodeKind::class)],
            'body' => ['required', 'string'],
            'hint' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'capture_field_codes' => ['nullable', 'array'],
            'capture_field_codes.*' => ['string', 'max:64'],
            'default_transitions' => ['nullable', 'array'],
            'default_transitions.*.customer_label' => ['nullable', 'string', 'max:500'],
            'default_transitions.*.sales_script_reaction_class_id' => ['nullable', 'integer', 'exists:sales_script_reaction_classes,id'],
        ];
    }
}

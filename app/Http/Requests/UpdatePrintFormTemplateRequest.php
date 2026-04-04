<?php

namespace App\Http\Requests;

use App\Models\PrintFormTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdatePrintFormTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        /** @var PrintFormTemplate $template */
        $template = $this->route('printFormTemplate');

        $contractorRules = ['nullable', 'integer'];

        if (Schema::hasTable('contractors')) {
            $contractorRules[] = Rule::exists('contractors', 'id');
        }

        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('print_form_templates', 'code')->ignore($template->id)],
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', 'string', Rule::in(array_column(PrintFormTemplate::entityTypeOptions(), 'value'))],
            'document_type' => ['required', 'string', Rule::in(array_column(PrintFormTemplate::documentTypeOptions(), 'value'))],
            'document_group' => ['required', 'string', Rule::in(array_column(PrintFormTemplate::documentGroupOptions(), 'value'))],
            'party' => ['required', 'string', Rule::in(array_column(PrintFormTemplate::partyOptions(), 'value'))],
            'source_type' => ['required', 'string', Rule::in(array_column(PrintFormTemplate::sourceTypeOptions(), 'value'))],
            'contractor_id' => $contractorRules,
            'is_default' => ['nullable', 'boolean'],
            'requires_internal_signature' => ['nullable', 'boolean'],
            'requires_counterparty_signature' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'variable_mappings' => ['nullable', 'array'],
            'variable_mappings.*.placeholder' => ['required_with:variable_mappings', 'string', 'max:255'],
            'variable_mappings.*.source_path' => ['nullable', 'string', 'max:255'],
            'source_file' => ['nullable', File::types(['docx'])->max(10 * 1024)],
        ];
    }
}

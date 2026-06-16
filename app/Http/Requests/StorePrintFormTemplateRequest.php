<?php

namespace App\Http\Requests;

use App\Models\PrintFormTemplate;
use App\Support\PrintFormTemplateDefaultScope;
use App\Support\PrintFormTemplateTransportScope;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StorePrintFormTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessSettingsSystem($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $contractorRules = ['nullable', 'integer'];

        if (Schema::hasTable('contractors')) {
            $contractorRules[] = Rule::exists('contractors', 'id');
        }

        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('print_form_templates', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', 'string', Rule::in(array_column(PrintFormTemplate::entityTypeOptions(), 'value'))],
            'document_type' => ['required', 'string', Rule::in(array_column(PrintFormTemplate::documentTypeOptions(), 'value'))],
            'document_group' => ['required', 'string', Rule::in(array_column(PrintFormTemplate::documentGroupOptions(), 'value'))],
            'party' => ['required', 'string', Rule::in(array_column(PrintFormTemplate::partyOptions(), 'value'))],
            'source_type' => ['required', 'string', Rule::in(array_column(PrintFormTemplate::sourceTypeOptions(), 'value'))],
            'contractor_id' => [
                ...$contractorRules,
                Rule::requiredIf($this->input('source_type') === 'external_docx'),
                Rule::prohibitedIf($this->input('source_type') === 'system'),
            ],
            'own_company_id' => $this->ownCompanyIdRules(),
            'transport_scope' => ['nullable', 'string', Rule::in(PrintFormTemplateTransportScope::values())],
            'is_default' => ['nullable', 'boolean'],
            'requires_internal_signature' => ['nullable', 'boolean'],
            'requires_counterparty_signature' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'variable_mappings' => ['nullable', 'array'],
            'variable_mappings.*.placeholder' => ['required_with:variable_mappings', 'string', 'max:255'],
            'variable_mappings.*.source_path' => ['nullable', 'string', 'max:255'],
            'internal_signature_placeholder' => ['nullable', 'string', 'max:255'],
            'internal_stamp_placeholder' => ['nullable', 'string', 'max:255'],
            'signature_image_width_mm' => ['nullable', 'numeric', 'min:5', 'max:200'],
            'signature_image_height_mm' => ['nullable', 'numeric', 'min:5', 'max:200'],
            'signature_image_offset_x_mm' => ['nullable', 'numeric', 'min:-200', 'max:200'],
            'signature_image_offset_y_mm' => ['nullable', 'numeric', 'min:-200', 'max:200'],
            'stamp_image_width_mm' => ['nullable', 'numeric', 'min:5', 'max:200'],
            'stamp_image_height_mm' => ['nullable', 'numeric', 'min:5', 'max:200'],
            'stamp_image_offset_x_mm' => ['nullable', 'numeric', 'min:-200', 'max:200'],
            'stamp_image_offset_y_mm' => ['nullable', 'numeric', 'min:-200', 'max:200'],
            'apply_crm_overlay_offsets' => ['nullable', 'boolean'],
            'signature_image_file' => ['nullable', File::types(['png', 'jpg', 'jpeg', 'webp'])->max(5 * 1024)],
            'stamp_image_file' => ['nullable', File::types(['png', 'jpg', 'jpeg', 'webp'])->max(5 * 1024)],
            'source_file' => [
                Rule::requiredIf($this->input('source_type') === 'external_docx'),
                File::types(['docx'])->max(10 * 1024),
            ],
        ];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function ownCompanyIdRules(): array
    {
        $rules = ['nullable', 'integer'];

        if (! Schema::hasTable('contractors')) {
            return $rules;
        }

        if (Schema::hasColumn('contractors', 'is_own_company')) {
            $rules[] = Rule::exists('contractors', 'id')->where('is_own_company', true);
        } else {
            $rules[] = Rule::exists('contractors', 'id');
        }

        if ($this->boolean('is_default') && PrintFormTemplateDefaultScope::defaultRequiresOwnCompany()) {
            $rules[0] = 'required';
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'own_company_id.required' => 'Укажите свою компанию для шаблона по умолчанию — у вас несколько «наших» компаний.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('source_type') !== 'external_docx') {
            $this->merge(['contractor_id' => null]);
        }

        if ($this->has('own_company_id') && $this->input('own_company_id') === '') {
            $this->merge(['own_company_id' => null]);
        }

        if (! $this->has('transport_scope') || trim((string) $this->input('transport_scope')) === '') {
            $this->merge(['transport_scope' => PrintFormTemplateTransportScope::ANY]);
        }
    }
}

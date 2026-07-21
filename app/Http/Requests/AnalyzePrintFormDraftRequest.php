<?php

namespace App\Http\Requests;

use App\Models\PrintFormTemplate;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class AnalyzePrintFormDraftRequest extends FormRequest
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
        $ownCompanyRules = ['nullable', 'integer'];

        if (Schema::hasTable('contractors')) {
            $contractorRules[] = Rule::exists('contractors', 'id');
            $ownCompanyRules[] = Rule::exists('contractors', 'id');
        }

        return [
            'source_file' => ['required', File::types(['docx'])->max(10 * 1024)],
            'party' => ['required', 'string', Rule::in(array_column(PrintFormTemplate::partyOptions(), 'value'))],
            'contractor_id' => $contractorRules,
            'own_company_id' => $ownCompanyRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_file.required' => 'Загрузите DOCX-черновик формы.',
            'party.required' => 'Укажите сторону шаблона (заказчик / перевозчик / внутренняя).',
        ];
    }
}

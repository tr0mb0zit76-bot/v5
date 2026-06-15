<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreManagementAccountingImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessPaymentReconcile($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_account_id' => ['nullable', 'integer', 'exists:management_bank_accounts,id'],
            'statement_file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'statement_file.required' => 'Выберите файл выписки.',
            'statement_file.mimes' => 'Поддерживается формат XLSX (реестр банковских документов).',
        ];
    }
}

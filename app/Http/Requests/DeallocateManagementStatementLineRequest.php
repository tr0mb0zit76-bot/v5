<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;

class DeallocateManagementStatementLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canManageStatementImport($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LinkSalesScriptLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads');
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
        ];
    }
}

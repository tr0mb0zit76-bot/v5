<?php

namespace App\Http\Requests\External;

use App\Support\ExternalParty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProvisionExternalUserInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'external_party' => ['nullable', 'string', Rule::in(ExternalParty::values())],
        ];
    }
}

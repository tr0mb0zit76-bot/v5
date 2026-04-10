<?php

namespace App\Http\Requests\SalesScripts;

use App\Enums\SalesScriptNodeKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNodeRequest extends FormRequest
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
            'client_key' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'string', Rule::enum(SalesScriptNodeKind::class)],
            'body' => ['required', 'string'],
            'hint' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

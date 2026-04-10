<?php

namespace App\Http\Requests\SalesScripts;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'entry_node_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}

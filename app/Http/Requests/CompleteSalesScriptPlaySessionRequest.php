<?php

namespace App\Http\Requests;

use App\Enums\SalesPlaySessionOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteSalesScriptPlaySessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|Rule>>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', Rule::enum(SalesPlaySessionOutcome::class)],
            'primary_reaction_class_id' => ['nullable', 'integer', 'exists:sales_script_reaction_classes,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

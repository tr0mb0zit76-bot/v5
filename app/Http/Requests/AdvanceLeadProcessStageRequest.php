<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdvanceLeadProcessStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'stage_id' => ['required', 'integer', 'exists:business_process_stages,id'],
            'close_outcome_primary_flag' => ['nullable', 'string', 'max:64'],
            'close_outcome_note' => ['nullable', 'string', 'max:255'],
        ];
    }
}

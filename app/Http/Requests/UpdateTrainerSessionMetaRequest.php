<?php

namespace App\Http\Requests;

use App\Enums\SalesTrainerDialogQuality;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainerSessionMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'trainer_assistant_instructions' => ['sometimes', 'nullable', 'string', 'max:8000'],
            'trainer_dialog_quality' => ['sometimes', 'nullable', Rule::enum(SalesTrainerDialogQuality::class)],
        ];
    }
}

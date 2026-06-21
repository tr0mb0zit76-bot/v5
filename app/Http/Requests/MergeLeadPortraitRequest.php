<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeLeadPortraitRequest extends FormRequest
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
            'qualification' => ['sometimes', 'array'],
            'qualification.need' => ['nullable', 'string', 'max:5000'],
            'qualification.timeline' => ['nullable', 'string', 'max:5000'],
            'qualification.authority' => ['nullable', 'string', 'max:5000'],
            'qualification.budget' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function qualificationPayload(): array
    {
        $fromRequest = $this->input('qualification');
        $fromLead = $this->route('lead')?->lead_qualification;

        return array_merge(
            is_array($fromLead) ? $fromLead : [],
            is_array($fromRequest) ? $fromRequest : [],
        );
    }
}

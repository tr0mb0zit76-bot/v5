<?php

namespace App\Http\Requests;

use App\Support\CrmAppearance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUiPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'button_radius' => ['sometimes', Rule::in([
                CrmAppearance::BUTTON_RADIUS_SHARP,
                CrmAppearance::BUTTON_RADIUS_ROUNDED,
            ])],
            'primary_accent' => ['sometimes', Rule::in([
                CrmAppearance::PRIMARY_ACCENT_EMERALD,
                CrmAppearance::PRIMARY_ACCENT_SKY,
            ])],
            'tab_style' => ['sometimes', Rule::in([
                CrmAppearance::TAB_STYLE_FILLED,
                CrmAppearance::TAB_STYLE_UNDERLINE,
            ])],
            'workspace_skin' => ['sometimes', Rule::in([
                CrmAppearance::WORKSPACE_SKIN_CLASSIC,
                CrmAppearance::WORKSPACE_SKIN_SKY,
            ])],
            'ag_grid_density' => ['sometimes', Rule::in(['compact', 'normal', 'comfortable'])],
        ];
    }
}

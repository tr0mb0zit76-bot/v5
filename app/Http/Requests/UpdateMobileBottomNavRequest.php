<?php

namespace App\Http\Requests;

use App\Support\MobileNavCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMobileBottomNavRequest extends FormRequest
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
            'mobile_nav_keys' => ['required', 'array', 'max:'.MobileNavCatalog::maxSelectable()],
            'mobile_nav_keys.*' => ['string', Rule::in(MobileNavCatalog::validKeys())],
        ];
    }
}

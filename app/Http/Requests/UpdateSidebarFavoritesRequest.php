<?php

namespace App\Http\Requests;

use App\Support\SidebarMenuCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSidebarFavoritesRequest extends FormRequest
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
            'sidebar_favorite_keys' => ['present', 'array', 'max:'.SidebarMenuCatalog::maxFavorites()],
            'sidebar_favorite_keys.*' => ['string', Rule::in(SidebarMenuCatalog::validKeys())],
        ];
    }
}

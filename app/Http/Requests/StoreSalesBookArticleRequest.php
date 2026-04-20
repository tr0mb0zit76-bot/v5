<?php

namespace App\Http\Requests;

use App\Models\SalesBookArticle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesBookArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'markdown_content' => ['nullable', 'string'],
            'html_content' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', Rule::exists((new SalesBookArticle)->getTable(), 'id')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}

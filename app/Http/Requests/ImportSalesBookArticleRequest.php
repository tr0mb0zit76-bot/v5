<?php

namespace App\Http\Requests;

use App\Models\SalesBookArticle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ImportSalesBookArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('parent_id') && $this->input('parent_id') === '') {
            $this->merge([
                'parent_id' => null,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['md', 'markdown', 'txt'])->max(5 * 1024),
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists((new SalesBookArticle)->getTable(), 'id')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}

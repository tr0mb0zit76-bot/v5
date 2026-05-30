<?php

namespace App\Http\Requests;

use App\Models\SalesBookArticle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveSalesBookArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $article = $this->route('salesBookArticle');
        $articleId = $article instanceof SalesBookArticle ? $article->id : null;

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists((new SalesBookArticle)->getTable(), 'id'),
                Rule::notIn($articleId !== null ? [$articleId] : []),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('parent_id') && $this->input('parent_id') === '') {
            $this->merge([
                'parent_id' => null,
            ]);
        }
    }
}

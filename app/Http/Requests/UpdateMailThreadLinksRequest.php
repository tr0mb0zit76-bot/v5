<?php

namespace App\Http\Requests;

use App\Support\VisibleOrderScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class UpdateMailThreadLinksRequest extends FormRequest
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
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'order_id' => ['nullable', 'integer', $this->visibleOrderExistsRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lead_id.exists' => 'Выбранный лид не найден.',
            'order_id.exists' => 'Выбранный заказ не найден или удалён.',
        ];
    }

    private function visibleOrderExistsRule(): Exists
    {
        return Rule::exists('orders', 'id')->where(function ($query): void {
            VisibleOrderScope::apply($query);
        });
    }
}

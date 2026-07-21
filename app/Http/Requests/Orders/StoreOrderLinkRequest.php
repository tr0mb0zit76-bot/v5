<?php

namespace App\Http\Requests\Orders;

use App\Models\Order;
use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessVisibilityArea($this->user(), 'orders');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'linked_order_id' => [
                'required',
                'integer',
                'exists:orders,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $order = $this->route('order');
                    if ($order instanceof Order && (int) $order->id === (int) $value) {
                        $fail('Нельзя связать заказ с самим собой.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'linked_order_id.required' => 'Выберите заказ для связи.',
            'linked_order_id.exists' => 'Заказ не найден.',
        ];
    }
}

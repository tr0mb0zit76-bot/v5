<?php

declare(strict_types=1);

namespace App\Http\Requests\Epd;

use App\Models\Order;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkEpdRegistryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return $user->isAdmin()
            || RoleAccess::canAccessVisibilityArea($user, 'documents')
            || RoleAccess::canAccessVisibilityArea($user, 'orders');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', Rule::exists('orders', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Укажите заказ для связи.',
            'order_id.exists' => 'Заказ не найден.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $orderId = (int) $this->input('order_id');
            if ($orderId <= 0) {
                return;
            }

            $order = Order::query()->find($orderId);
            $user = $this->user();
            if ($order === null || $user === null) {
                return;
            }

            if (! OrderViewAuthorization::userCanMutateOrder($user, $order)) {
                $validator->errors()->add('order_id', 'Нет доступа к выбранному заказу.');
            }
        });
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Support\OrderInlineFieldCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateInlineOrderFieldRequest extends FormRequest
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
            'field' => ['required', 'string', Rule::in(OrderInlineFieldCatalog::allowedFields())],
            'value' => ['nullable'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            $field = $this->input('field');
            if (! is_string($field)) {
                return;
            }

            $user = $this->user();
            $order = $this->route('order');

            if (! $user || ! $order instanceof Order) {
                return;
            }

            try {
                OrderInlineFieldCatalog::validate($user, $order, $field, $this->input('value'));
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }

    /**
     * @return array{field: string, value: mixed}
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();
        $field = (string) $validated['field'];

        return OrderInlineFieldCatalog::normalizePayload($field, $validated['value'] ?? null);
    }
}

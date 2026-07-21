<?php

namespace App\Http\Requests;

use App\Enums\OrderClaimParty;
use App\Enums\OrderClaimStatus;
use App\Enums\OrderClaimType;
use App\Models\Order;
use App\Services\OrderClaimService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('order');

        if ($user === null || ! $order instanceof Order) {
            return false;
        }

        return app(OrderClaimService::class)->userCanMutate($user, $order);
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('amount_risk') === '' || $this->input('amount_risk') === null) {
            $this->merge(['amount_risk' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'party' => ['required', 'string', Rule::enum(OrderClaimParty::class)],
            'type' => ['required', 'string', Rule::enum(OrderClaimType::class)],
            'status' => ['nullable', 'string', Rule::enum(OrderClaimStatus::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount_risk' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

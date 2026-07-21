<?php

namespace App\Http\Requests;

use App\Enums\OrderClaimParty;
use App\Enums\OrderClaimStatus;
use App\Enums\OrderClaimType;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Services\OrderClaimService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('order');
        $claim = $this->route('claim');

        if ($user === null || ! $order instanceof Order || ! $claim instanceof OrderClaim) {
            return false;
        }

        if ((int) $claim->order_id !== (int) $order->id) {
            return false;
        }

        return app(OrderClaimService::class)->userCanMutate($user, $order);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'party' => ['sometimes', 'string', Rule::enum(OrderClaimParty::class)],
            'type' => ['sometimes', 'string', Rule::enum(OrderClaimType::class)],
            'status' => ['sometimes', 'string', Rule::enum(OrderClaimStatus::class)],
            'title' => ['sometimes', 'string', 'max:255'],
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

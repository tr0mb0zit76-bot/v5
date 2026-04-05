<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFinanceDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:orders,id'],
            'document_type' => ['required', 'in:invoice,upd'],
            'amount' => ['required', 'numeric', 'min:0'],
            'number' => ['nullable', 'string', 'max:50'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_basis' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,issued,sent,signed'],
        ];
    }
}

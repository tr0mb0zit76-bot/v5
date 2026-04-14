<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreDocumentRegistryRequest extends FormRequest
{
    private const CONTRACT_TYPES = ['contract', 'contract_request'];

    private const ALLOWED_TYPES = [
        'request',
        'contract',
        'contract_request',
        'waybill',
        'cmr',
        'upd',
        'invoice',
        'invoice_factura',
        'act',
        'packing_list',
        'customs_declaration',
        'other',
    ];

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
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'type' => ['required', Rule::in(self::ALLOWED_TYPES)],
            'party' => ['required', Rule::in(['customer', 'carrier', 'internal'])],
            'number' => ['nullable', 'string', 'max:255'],
            'document_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'pending', 'signed', 'sent'])],
            'file' => ['required', File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'])->max(3072)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');
                if (! $file instanceof UploadedFile) {
                    return;
                }

                $type = (string) $this->input('type', '');
                $maxKb = in_array($type, self::CONTRACT_TYPES, true) ? 3072 : 1024;
                $maxBytes = $maxKb * 1024;
                if ((int) $file->getSize() > $maxBytes) {
                    $validator->errors()->add('file', $maxKb === 3072
                        ? 'Для договоров допустим размер файла до 3 МБ.'
                        : 'Для этого типа документа допустим размер файла до 1 МБ.');
                }
            },
        ];
    }
}

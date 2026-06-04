<?php

namespace App\Http\Requests\Concerns;

use App\Enums\OrderNumberSegmentType;
use App\Enums\OrderNumberSequenceScope;
use Illuminate\Validation\Rule;

trait ValidatesOrderNumberingRule
{
    /**
     * @return array<string, mixed>
     */
    protected function orderNumberingRuleFieldRules(?int $ignoreRuleId = null): array
    {
        $segmentTypes = array_map(fn (OrderNumberSegmentType $case): string => $case->value, OrderNumberSegmentType::cases());
        $scopes = array_map(fn (OrderNumberSequenceScope $case): string => $case->value, OrderNumberSequenceScope::cases());

        return [
            'cipher' => [
                'required',
                'string',
                'max:32',
                'regex:/^[a-z0-9_-]+$/i',
                Rule::unique('order_numbering_rules', 'cipher')->ignore($ignoreRuleId),
            ],
            'own_company_id' => [
                'required',
                'integer',
                Rule::exists('contractors', 'id')->where('is_own_company', true),
                Rule::unique('order_numbering_rules', 'own_company_id')->ignore($ignoreRuleId),
            ],
            'separator' => ['required', 'string', 'max:3'],
            'prefix_type' => ['required', 'string', Rule::in($segmentTypes)],
            'prefix_value' => ['nullable', 'string', 'max:64'],
            'body_type' => ['required', 'string', Rule::in($segmentTypes)],
            'body_value' => ['nullable', 'string', 'max:64'],
            'suffix_type' => ['required', 'string', Rule::in($segmentTypes)],
            'suffix_value' => ['nullable', 'string', 'max:64'],
            'sequence_pad' => ['required', 'integer', 'min:0', 'max:8'],
            'sequence_scope' => ['required', 'string', Rule::in($scopes)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function orderNumberingRuleMessages(): array
    {
        return [
            'cipher.regex' => 'Шифр: только латиница, цифры, дефис и подчёркивание.',
            'own_company_id.exists' => 'Выберите свою компанию из справочника.',
            'own_company_id.unique' => 'Для этой компании правило уже задано.',
        ];
    }
}

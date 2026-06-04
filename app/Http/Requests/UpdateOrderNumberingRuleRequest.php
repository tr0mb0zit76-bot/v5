<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesOrderNumberingRule;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderNumberingRuleRequest extends FormRequest
{
    use ValidatesOrderNumberingRule;

    public function authorize(): bool
    {
        return RoleAccess::canAccessSettingsSystem($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ruleId = $this->route('orderNumberingRule');

        return $this->orderNumberingRuleFieldRules(is_object($ruleId) ? $ruleId->id : (int) $ruleId);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->orderNumberingRuleMessages();
    }
}

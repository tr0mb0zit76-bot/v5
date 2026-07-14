<?php

namespace App\Http\Requests;

use App\Enums\ConversationParticipantRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationParticipantRoleRequest extends FormRequest
{
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
            'role' => [
                'required',
                Rule::enum(ConversationParticipantRole::class)
                    ->only([
                        ConversationParticipantRole::Admin,
                        ConversationParticipantRole::Member,
                    ]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.enum' => 'Участнику можно назначить роль администратора или участника.',
        ];
    }
}

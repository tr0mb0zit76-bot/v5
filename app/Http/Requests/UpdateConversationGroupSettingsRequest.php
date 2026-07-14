<?php

namespace App\Http\Requests;

use App\Enums\ConversationPostingPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationGroupSettingsRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'posting_policy' => ['sometimes', 'required', Rule::enum(ConversationPostingPolicy::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название группы.',
            'title.max' => 'Название группы не должно превышать 255 символов.',
            'posting_policy.enum' => 'Выбран неизвестный режим отправки сообщений.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreChatMessageRequest extends FormRequest
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
        $conversation = $this->route('conversation');
        $conversationId = $conversation instanceof Conversation ? $conversation->id : 0;

        return [
            'body' => ['nullable', 'required_without:attachments', 'string', 'max:8000'],
            'client_message_id' => ['nullable', 'uuid'],
            'recipient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'reply_to_message_id' => [
                'nullable',
                'integer',
                Rule::exists('chat_messages', 'id')
                    ->where('conversation_id', $conversationId),
            ],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'required',
                File::types([
                    'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif',
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                    'odt', 'ods', 'rtf', 'csv', 'txt', 'zip', 'rar', '7z',
                ])->max(20 * 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required_without' => 'Введите сообщение или прикрепите файл.',
            'body.max' => 'Сообщение не должно превышать 8000 символов.',
            'reply_to_message_id.exists' => 'Цитируемое сообщение не найдено в этом чате.',
            'attachments.max' => 'К одному сообщению можно прикрепить не более 10 файлов.',
            'attachments.*.max' => 'Размер каждого файла не должен превышать 20 МБ.',
            'attachments.*.mimes' => 'Этот тип файла нельзя отправить в чат.',
        ];
    }
}

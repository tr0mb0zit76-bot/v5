<?php

namespace App\Http\Requests;

use App\Services\Agents\CommandBarAttachmentService;
use App\Support\CommandBarHistoryLimits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CommandBarAgentChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('history') && is_string($this->input('history'))) {
            $decoded = json_decode($this->input('history'), true);

            if (is_array($decoded)) {
                $this->merge(['history' => $decoded]);
            }
        }

        if ($this->has('history_extended')) {
            $this->merge([
                'history_extended' => filter_var($this->input('history_extended'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxBytes = max(1024, CommandBarAttachmentService::maxUploadBytes());
        $maxHistory = CommandBarHistoryLimits::requestMax(
            $this->user(),
            $this->boolean('history_extended'),
        );

        return [
            'message' => ['nullable', 'string', 'max:4000'],
            'agent_slug' => ['nullable', 'string', 'max:32', 'regex:/^[a-z][a-z0-9_-]{0,31}$/'],
            'history' => ['nullable', 'array', 'max:'.$maxHistory],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:8000'],
            'history_extended' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:'.max(1, (int) config('ai.command_bar.max_attachment_files', 3))],
            'attachments.*' => ['file', 'mimes:pdf,docx,jpg,jpeg,png,webp', 'max:'.(int) ceil($maxBytes / 1024)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $message = trim((string) $this->input('message', ''));
            $attachments = $this->file('attachments');

            if ($message === '' && ($attachments === null || $attachments === [])) {
                $validator->errors()->add('message', 'Введите сообщение или приложите файл.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.max' => 'Сообщение слишком длинное (максимум 4000 символов).',
            'attachments.max' => 'Слишком много файлов за один запрос.',
            'attachments.*.mimes' => 'Поддерживаются PDF, DOCX и изображения (JPG, PNG, WEBP).',
            'attachments.*.max' => 'Файл слишком большой для загрузки.',
        ];
    }
}

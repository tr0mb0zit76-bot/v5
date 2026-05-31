<?php

namespace App\Services\Orders;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\OrderIntakeDraft;
use App\Models\User;
use App\Services\Agents\AiRequestGate;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\DocumentStorageService;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use App\Support\AiChannel;
use App\Support\OrderIntakeSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderDocumentIntakeService
{
    public function __construct(
        private readonly DocumentTextExtractor $textExtractor,
        private readonly ChatCompletionClient $chat,
        private readonly ExternalLlmPayloadSanitizer $sanitizer,
        private readonly AiRequestGate $aiGate,
        private readonly OrderIntakeContractorResolver $contractorResolver,
        private readonly DocumentStorageService $documentStorage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extractFromUpload(User $user, UploadedFile $file): array
    {
        if (! (bool) config('ai.order_intake.enabled', true)) {
            throw ValidationException::withMessages([
                'file' => 'Распознавание заявок отключено в конфигурации.',
            ]);
        }

        if ($this->aiGate->channelFor('order_intake', $user) === AiChannel::LocalOnly) {
            throw ValidationException::withMessages([
                'file' => 'Для распознавания заявок нужен DEEPSEEK_API_KEY.',
            ]);
        }

        $extraction = $this->textExtractor->extractFromUpload($file);
        $text = trim($extraction['text']);
        $warnings = $extraction['warnings'];

        $maxChars = max(2000, (int) config('ai.order_intake.max_text_chars', 12000));
        if ($text === '') {
            throw ValidationException::withMessages([
                'file' => $warnings[0] ?? 'Не удалось извлечь текст из файла.',
            ]);
        }

        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars);
            $warnings[] = 'Текст заявки обрезан до '.$maxChars.' символов для LLM.';
        }

        try {
            $extracted = $this->structureWithLlm($text);
        } catch (Throwable $throwable) {
            Log::warning('order_intake_llm_failed', [
                'user_id' => $user->id,
                'message' => $throwable->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'file' => 'Не удалось структурировать заявку: '.$throwable->getMessage(),
            ]);
        }

        $customer = is_array($extracted['customer'] ?? null) ? $extracted['customer'] : [];
        $contractorMatches = $this->contractorResolver->match($user, $customer);
        $wizard = OrderIntakeSchema::toWizardPatch($extracted, $contractorMatches);

        $stored = $this->documentStorage->storeOrderUpload($file, null);

        $draft = OrderIntakeDraft::query()->create([
            'user_id' => $user->id,
            'source_original_name' => $file->getClientOriginalName(),
            'source_mime_type' => $file->getMimeType(),
            'source_storage_path' => $stored['file_path'],
            'source_storage_driver' => $stored['storage_driver'],
            'source_text_hash' => hash('sha256', $text),
            'source_text_length' => mb_strlen($text),
            'model' => (string) config('ai.inference.deepseek.default_model', 'deepseek-chat'),
            'confidence' => isset($extracted['confidence']) ? (float) $extracted['confidence'] : null,
            'extracted_payload' => $extracted,
            'wizard_patch' => $wizard['patch'],
            'warnings' => $warnings,
            'matched_contractors' => $contractorMatches,
        ]);

        return [
            'draft_id' => $draft->id,
            'confidence' => $draft->confidence,
            'extraction_method' => $extraction['method'],
            'warnings' => $warnings,
            'preview' => $wizard['preview'],
            'wizard_patch' => $wizard['patch'],
            'matched_contractors' => $contractorMatches,
            'extracted' => $extracted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function structureWithLlm(string $text): array
    {
        $messages = [
            ['role' => 'system', 'content' => OrderIntakeSchema::llmSystemPrompt()],
            ['role' => 'user', 'content' => "Текст заявки:\n\n".$text],
        ];

        $messages = $this->sanitizer->sanitizeMessages($messages, 'command_bar');

        $content = $this->chat->chat($messages, [
            'temperature' => (float) config('ai.order_intake.temperature', 0.1),
            'max_tokens' => (int) config('ai.order_intake.max_tokens', 2500),
        ]);

        return OrderIntakeSchema::parseLlmJson($content);
    }
}

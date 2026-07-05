<?php

namespace App\Services\Orders;

use App\Models\OrderIntakeDraft;
use App\Models\User;
use App\Services\Agents\AiRequestGate;
use App\Services\Ai\AiInteractionRecorder;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\DocumentStorageService;
use App\Services\OrderIntakeGoldenLibraryService;
use App\Services\TransportTextIntakeService;
use App\Support\AiChannel;
use App\Support\OrderIntakeDraftNavigation;
use App\Support\OrderIntakePhraseNormalizer;
use App\Support\OrderIntakeSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderDocumentIntakeService
{
    public function __construct(
        private readonly DocumentTextExtractor $textExtractor,
        private readonly TransportTextIntakeService $transportTextIntakeService,
        private readonly AiRequestGate $aiGate,
        private readonly OrderIntakeContractorResolver $contractorResolver,
        private readonly DocumentStorageService $documentStorage,
        private readonly AiInteractionRecorder $interactionRecorder,
        private readonly OrderIntakeGoldenLibraryService $goldenLibrary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extractFromText(
        User $user,
        string $instruction,
        string $sourceLabel = 'text-instruction',
        string $sourceKind = 'text',
    ): array {
        $text = trim($instruction);

        if ($text === '') {
            throw ValidationException::withMessages([
                'instruction' => 'Укажите текст заявки (маршрут, груз, ставки, условия оплаты).',
            ]);
        }

        $maxChars = max(500, (int) config('ai.order_intake.max_text_chars', 12000));

        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars);
            $warnings = ['Текст заявки обрезан до '.$maxChars.' символов для LLM.'];
        } else {
            $warnings = [];
        }

        return $this->extractFromPlainText(
            $user,
            $text,
            $warnings,
            Str::limit($sourceLabel, 200, ''),
            'text/plain',
            null,
            null,
            null,
            null,
            $text,
            $sourceKind,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function extractFromUpload(User $user, UploadedFile $file): array
    {
        $startedAt = hrtime(true);

        $this->assertIntakeEnabled($user);

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

        $stored = $this->documentStorage->storeOrderUpload($file, null);

        return $this->extractFromPlainText(
            $user,
            $text,
            $warnings,
            $file->getClientOriginalName(),
            $file->getMimeType(),
            $stored['file_path'],
            $stored['storage_driver'],
            $extraction['method'],
            $startedAt,
            null,
            'file',
        );
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function extractFromPlainText(
        User $user,
        string $text,
        array $warnings,
        string $sourceName,
        ?string $sourceMimeType,
        ?string $sourceStoragePath,
        ?string $sourceStorageDriver,
        ?string $extractionMethod,
        ?int $startedAt = null,
        ?string $userInstruction = null,
        string $sourceKind = 'text',
    ): array {
        $startedAt ??= hrtime(true);

        $this->assertIntakeEnabled($user);

        try {
            $extracted = $this->transportTextIntakeService->structureWithLlm($user, $text);
            $extracted = OrderIntakePhraseNormalizer::normalizeExtracted($extracted, $user);
        } catch (Throwable $throwable) {
            Log::warning('order_intake_llm_failed', [
                'user_id' => $user->id,
                'message' => $throwable->getMessage(),
            ]);

            $this->interactionRecorder->recordIntakeExtracted(
                $user,
                false,
                null,
                null,
                $throwable->getMessage(),
                (int) ((hrtime(true) - $startedAt) / 1_000_000),
                ['source_name' => $sourceName],
            );

            $field = $sourceStoragePath === null ? 'instruction' : 'file';

            throw ValidationException::withMessages([
                $field => 'Не удалось структурировать заявку: '.$throwable->getMessage(),
            ]);
        }

        $customer = is_array($extracted['customer'] ?? null) ? $extracted['customer'] : [];
        $carrier = is_array($extracted['carrier'] ?? null) ? $extracted['carrier'] : [];
        $ownCompany = is_array($extracted['own_company'] ?? null) ? $extracted['own_company'] : [];
        $contractorMatches = $this->contractorResolver->matchParties($user, $customer, $carrier);
        $ownCompanyMatch = $this->contractorResolver->matchOwnCompany($user, $ownCompany);
        $wizard = OrderIntakeSchema::toWizardPatch($extracted, $contractorMatches, $ownCompanyMatch);

        $draft = OrderIntakeDraft::query()->create([
            'user_id' => $user->id,
            'source_original_name' => $sourceName,
            'source_mime_type' => $sourceMimeType,
            'source_storage_path' => $sourceStoragePath,
            'source_storage_driver' => $sourceStorageDriver,
            'source_text_hash' => hash('sha256', $text),
            'source_text_length' => mb_strlen($text),
            'model' => (string) config('ai.inference.deepseek.default_model', 'deepseek-chat'),
            'confidence' => isset($extracted['confidence']) ? (float) $extracted['confidence'] : null,
            'extracted_payload' => $extracted,
            'wizard_patch' => $wizard['patch'],
            'warnings' => $warnings,
            'matched_contractors' => $contractorMatches,
        ]);

        $meta = ['source_name' => $sourceName];

        if ($extractionMethod !== null) {
            $meta['extraction_method'] = $extractionMethod;
        }

        $this->interactionRecorder->recordIntakeExtracted(
            $user,
            true,
            $draft->confidence !== null ? (float) $draft->confidence : null,
            $draft->id,
            null,
            (int) ((hrtime(true) - $startedAt) / 1_000_000),
            $meta,
        );

        try {
            $this->goldenLibrary->openPendingForDraft(
                $user,
                $draft,
                $userInstruction ?? $text,
                $sourceKind,
                $extracted,
                $wizard['patch'],
                $warnings,
            );
        } catch (Throwable $throwable) {
            Log::warning('order_intake_golden_library_open_failed', [
                'user_id' => $user->id,
                'draft_id' => $draft->id,
                'message' => $throwable->getMessage(),
            ]);
        }

        $result = [
            'draft_id' => $draft->id,
            'wizard_path' => OrderIntakeDraftNavigation::wizardPathForDraft($draft->id),
            'confidence' => $draft->confidence,
            'warnings' => $warnings,
            'preview' => $wizard['preview'],
            'wizard_patch' => $wizard['patch'],
            'matched_contractors' => $contractorMatches,
            'extracted' => $extracted,
            'note' => 'Черновик заявки сохранён. Откройте мастер: wizard_path (в command bar откроется автоматически). Проверьте поля перед сохранением.',
        ];

        if ($extractionMethod !== null) {
            $result['extraction_method'] = $extractionMethod;
        }

        return $result;
    }

    private function assertIntakeEnabled(User $user): void
    {
        if (! (bool) config('ai.order_intake.enabled', true)) {
            throw ValidationException::withMessages([
                'instruction' => 'Распознавание заявок отключено в конфигурации.',
            ]);
        }

        if ($this->aiGate->channelFor('order_intake', $user) === AiChannel::LocalOnly) {
            throw ValidationException::withMessages([
                'instruction' => 'Для распознавания заявок нужен DEEPSEEK_API_KEY.',
            ]);
        }
    }
}

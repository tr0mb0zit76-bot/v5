<?php

namespace App\Services\Contractor;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\Contractor;
use App\Models\ContractorInsightDraft;
use App\Models\ContractorPortrait;
use App\Models\MailMessage;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Services\Agents\AiRequestGate;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use App\Support\ActivityEventType;
use App\Support\AiChannel;
use App\Support\ContractorInsightDraftFieldCatalog;
use App\Support\ContractorPortraitDictionary;
use App\Support\MailSync\MailMessageBodyPresenter;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ContractorInsightDraftService
{
    public function __construct(
        private readonly ContractorPortraitService $portraitService,
        private readonly ActivityLedgerService $activityLedger,
        private readonly ChatCompletionClient $chat,
        private readonly ExternalLlmPayloadSanitizer $sanitizer,
        private readonly AiRequestGate $aiGate,
    ) {}

    /**
     * @return list<ContractorInsightDraft>
     */
    public function extractFromMailMessage(MailMessage $message, Contractor $contractor, User $user): array
    {
        $this->assertAiAvailable($user);

        $body = MailMessageBodyPresenter::plainText($message);

        if ($body === null || trim($body) === '') {
            throw ValidationException::withMessages([
                'mail_message_id' => 'В письме нет текста для извлечения фактов.',
            ]);
        }

        $parsed = $this->requestInsightsFromLlm($message, $body);
        $created = [];

        foreach ($parsed as $item) {
            if (! is_array($item)) {
                continue;
            }

            $fieldKey = (string) ($item['field_key'] ?? '');
            $normalized = ContractorInsightDraftFieldCatalog::normalizeProposedValue(
                $fieldKey,
                $item['proposed_value'] ?? null,
            );

            if ($normalized === null) {
                continue;
            }

            if ($this->pendingDraftExists($contractor, $fieldKey, ContractorInsightDraft::SOURCE_MAIL_MESSAGE, $message->id)) {
                continue;
            }

            $created[] = ContractorInsightDraft::query()->create([
                'contractor_id' => $contractor->id,
                'field_key' => $fieldKey,
                'proposed_value' => $normalized,
                'source_type' => ContractorInsightDraft::SOURCE_MAIL_MESSAGE,
                'source_id' => $message->id,
                'confidence' => isset($item['confidence']) ? (float) $item['confidence'] : null,
                'status' => ContractorInsightDraft::STATUS_PENDING,
            ]);
        }

        return $created;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function serializePendingForContractor(Contractor $contractor): Collection
    {
        return ContractorInsightDraft::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', ContractorInsightDraft::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ContractorInsightDraft $draft): array => $this->serializeDraft($draft));
    }

    /**
     * @return array{draft: array<string, mixed>, portrait: array<string, mixed>}
     */
    public function accept(ContractorInsightDraft $draft, Contractor $contractor, User $user): array
    {
        if (! $draft->isPending()) {
            throw ValidationException::withMessages([
                'draft' => 'Предложение уже обработано.',
            ]);
        }

        if ((int) $draft->contractor_id !== (int) $contractor->id) {
            throw ValidationException::withMessages([
                'draft' => 'Предложение не относится к этому контрагенту.',
            ]);
        }

        $updates = $this->buildPortraitUpdatesFromDraft($contractor, $draft);

        if ($updates === []) {
            throw ValidationException::withMessages([
                'draft' => 'Нечего применять: поле уже заполнено или значение не подходит.',
            ]);
        }

        $portrait = $this->portraitService->updatePortrait($contractor, $updates, $user);

        $draft->update([
            'status' => ContractorInsightDraft::STATUS_ACCEPTED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        $this->activityLedger->record(
            $contractor,
            ActivityEventType::PortraitInsightAccepted,
            'Факт в портрет принят',
            ContractorInsightDraftFieldCatalog::label($draft->field_key),
            [
                'field_key' => $draft->field_key,
                'draft_id' => $draft->id,
                'source_type' => $draft->source_type,
                'source_id' => $draft->source_id,
            ],
            null,
            $user,
            $draft,
        );

        $contractor->load(['portrait', 'contacts']);

        return [
            'draft' => $this->serializeDraft($draft->fresh()),
            'portrait' => $this->portraitService->serializePortrait($portrait, $contractor),
        ];
    }

    /**
     * @return array{draft: array<string, mixed>}
     */
    public function reject(ContractorInsightDraft $draft, Contractor $contractor, User $user): array
    {
        if (! $draft->isPending()) {
            throw ValidationException::withMessages([
                'draft' => 'Предложение уже обработано.',
            ]);
        }

        if ((int) $draft->contractor_id !== (int) $contractor->id) {
            throw ValidationException::withMessages([
                'draft' => 'Предложение не относится к этому контрагенту.',
            ]);
        }

        $draft->update([
            'status' => ContractorInsightDraft::STATUS_REJECTED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        return [
            'draft' => $this->serializeDraft($draft->fresh()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeDraft(ContractorInsightDraft $draft): array
    {
        $display = ContractorInsightDraftFieldCatalog::displayValue(
            $draft->field_key,
            $draft->proposed_value,
        );

        return [
            'id' => $draft->id,
            'field_key' => $draft->field_key,
            'field_label' => ContractorInsightDraftFieldCatalog::label($draft->field_key),
            'proposed_value' => $draft->proposed_value,
            'proposed_display' => $display['text'] ?? '',
            'source_type' => $draft->source_type,
            'source_id' => $draft->source_id,
            'source_label' => $this->sourceLabel($draft),
            'source_url' => $this->sourceUrl($draft),
            'confidence' => $draft->confidence !== null ? (float) $draft->confidence : null,
            'status' => $draft->status,
            'created_at' => optional($draft->created_at)?->toIso8601String(),
        ];
    }

    private function sourceLabel(ContractorInsightDraft $draft): ?string
    {
        return match ($draft->source_type) {
            ContractorInsightDraft::SOURCE_MAIL_MESSAGE => 'Письмо',
            default => null,
        };
    }

    private function sourceUrl(ContractorInsightDraft $draft): ?string
    {
        if ($draft->source_type !== ContractorInsightDraft::SOURCE_MAIL_MESSAGE || $draft->source_id === null) {
            return null;
        }

        $message = MailMessage::query()->with('thread:id')->find($draft->source_id);

        if ($message?->thread === null) {
            return null;
        }

        return route('mail.threads.show', $message->thread);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestInsightsFromLlm(MailMessage $message, string $body): array
    {
        $allowedFields = implode(', ', ContractorInsightDraftFieldCatalog::WHITELIST);

        $systemPrompt = <<<TEXT
Ты помощник менеджера экспедиторской компании. Из текста письма извлеки факты для портрета клиента.

Разрешённые field_key: {$allowedFields}.
- success_criteria, internal_notes — строка без ПД (email, телефоны не цитируй).
- preferred_channel — phone|email|messenger|meeting
- price_sensitivity — low|medium|high
- typical_objections — массив тегов из: price, timing, competitor, documents, capacity, trust

Только явные факты из письма. Ответ — JSON-массив:
[{"field_key":"...","proposed_value":"...","confidence":0.0-1.0}]
Если фактов нет — [].
TEXT;

        $userPrompt = 'Тема: '.(string) ($message->subject ?? '(без темы)')."\n\n".$body;

        $messages = $this->sanitizer->sanitizeMessages([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], 'command_bar');

        $content = trim($this->chat->chat($messages, [
            'temperature' => (float) config('ai.insight_drafts.temperature', 0.2),
            'max_tokens' => (int) config('ai.insight_drafts.max_tokens', 900),
        ]));

        return $this->parseJsonArray($content);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseJsonArray(string $content): array
    {
        $trimmed = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/u', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Не удалось разобрать JSON-ответ модели.');
        }

        return array_values(array_filter($decoded, fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPortraitUpdatesFromDraft(Contractor $contractor, ContractorInsightDraft $draft): array
    {
        $portrait = $this->portraitService->getOrCreate($contractor);
        $value = $draft->proposed_value;
        $fieldKey = $draft->field_key;

        return match ($fieldKey) {
            'success_criteria' => blank($portrait->success_criteria) && is_string($value)
                ? ['success_criteria' => $value]
                : [],
            'preferred_channel' => ($portrait->preferred_channel ?? ContractorPortraitDictionary::UNKNOWN) === ContractorPortraitDictionary::UNKNOWN
                && is_string($value)
                ? ['preferred_channel' => $value]
                : [],
            'price_sensitivity' => ($portrait->price_sensitivity ?? ContractorPortraitDictionary::UNKNOWN) === ContractorPortraitDictionary::UNKNOWN
                && is_string($value)
                ? ['price_sensitivity' => $value]
                : [],
            'typical_objections' => $this->mergeObjectionTags($portrait, is_array($value) ? $value : []),
            'internal_notes' => is_string($value)
                ? $this->internalNotesUpdate($portrait, $value, $draft)
                : [],
            default => [],
        };
    }

    /**
     * @param  list<string>  $tags
     * @return array<string, mixed>
     */
    private function mergeObjectionTags(ContractorPortrait $portrait, array $tags): array
    {
        if ($tags === []) {
            return [];
        }

        $existing = is_array($portrait->typical_objections) ? $portrait->typical_objections : [];

        return [
            'typical_objections' => array_values(array_unique([...$existing, ...$tags])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function internalNotesUpdate(ContractorPortrait $portrait, string $value, ContractorInsightDraft $draft): array
    {
        $next = $this->appendInternalNote($portrait, $value, $draft);
        $current = trim((string) ($portrait->internal_notes ?? ''));

        if ($next === $current) {
            return [];
        }

        return ['internal_notes' => $next];
    }

    private function appendInternalNote(ContractorPortrait $portrait, string $value, ContractorInsightDraft $draft): string
    {
        $prefix = match ($draft->source_type) {
            ContractorInsightDraft::SOURCE_MAIL_MESSAGE => 'Из письма',
            default => 'Из источника',
        };

        $line = trim($prefix.': '.$value);
        $current = trim((string) ($portrait->internal_notes ?? ''));

        if ($current === '') {
            return $line;
        }

        if (str_contains($current, $value)) {
            return $current;
        }

        return $current."\n".$line;
    }

    private function pendingDraftExists(
        Contractor $contractor,
        string $fieldKey,
        string $sourceType,
        ?int $sourceId,
    ): bool {
        return ContractorInsightDraft::query()
            ->where('contractor_id', $contractor->id)
            ->where('field_key', $fieldKey)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('status', ContractorInsightDraft::STATUS_PENDING)
            ->exists();
    }

    private function assertAiAvailable(User $user): void
    {
        if ($this->aiGate->channelFor('command_bar', $user) === AiChannel::LocalOnly) {
            throw ValidationException::withMessages([
                'mail_message_id' => $this->aiGate->unavailableMessage('command_bar'),
            ]);
        }
    }
}

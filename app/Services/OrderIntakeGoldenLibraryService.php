<?php

namespace App\Services;

use App\Enums\OrderIntakeGoldenRecordStatus;
use App\Models\OrderIntakeDraft;
use App\Models\OrderIntakeGoldenRecord;
use App\Models\User;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class OrderIntakeGoldenLibraryService
{
    private const string DIALOG_CACHE_PREFIX = 'order_intake_dialog_learnings:';

    private const string ACTIVE_DRAFT_CACHE_PREFIX = 'order_intake_active_draft:';

    private const int CACHE_TTL_SECONDS = 86_400;

    public function __construct(
        private readonly ExternalLlmPayloadSanitizer $sanitizer,
    ) {}

    public function tableAvailable(): bool
    {
        return Schema::hasTable('order_intake_golden_records');
    }

    /**
     * @param  array<string, mixed>  $extractedPayload
     * @param  array<string, mixed>  $wizardPatch
     * @param  list<string>  $warnings
     */
    public function openPendingForDraft(
        User $user,
        OrderIntakeDraft $draft,
        ?string $userInstruction,
        string $sourceKind,
        array $extractedPayload,
        array $wizardPatch,
        array $warnings = [],
    ): void {
        if (! $this->tableAvailable()) {
            return;
        }

        $this->discardOtherPendingForUser($user, $draft->id);

        $instruction = $this->sanitizeInstruction($userInstruction);
        $dialogLearnings = $this->pullDialogLearningsFromCache($user);

        OrderIntakeGoldenRecord::query()->updateOrCreate(
            ['order_intake_draft_id' => $draft->id],
            [
                'user_id' => $user->id,
                'status' => OrderIntakeGoldenRecordStatus::Pending,
                'source_kind' => $sourceKind,
                'user_instruction' => $instruction !== '' ? $instruction : null,
                'dialog_learnings' => $dialogLearnings,
                'proposed_snapshot' => $this->sanitizeSnapshot([
                    'extracted_payload' => $extractedPayload,
                    'wizard_patch' => $wizardPatch,
                    'warnings' => $warnings,
                    'confidence' => $draft->confidence,
                    'matched_contractors' => $draft->matched_contractors ?? [],
                ]),
                'applied_snapshot' => null,
                'order_id' => null,
                'committed_at' => null,
            ],
        );

        $this->rememberActiveDraft($user, (int) $draft->id);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function recordDialogLearning(
        User $user,
        string $sourcePhrase,
        string $canonicalValue,
        string $field,
        ?int $draftId = null,
    ): array {
        $entry = [
            'source_phrase' => trim($sourcePhrase),
            'canonical_value' => trim($canonicalValue),
            'field' => $field,
            'recorded_at' => now()->toIso8601String(),
        ];

        if ($entry['source_phrase'] === '' || $entry['canonical_value'] === '') {
            return ['ok' => false, 'message' => 'Пустая фраза или значение.'];
        }

        $targetDraftId = $draftId ?? $this->activeDraftIdForUser($user);

        if ($targetDraftId !== null && $this->tableAvailable()) {
            $record = OrderIntakeGoldenRecord::query()
                ->where('order_intake_draft_id', $targetDraftId)
                ->where('user_id', $user->id)
                ->where('status', OrderIntakeGoldenRecordStatus::Pending)
                ->first();

            if ($record !== null) {
                $learnings = is_array($record->dialog_learnings) ? $record->dialog_learnings : [];
                $learnings[] = $entry;
                $record->update(['dialog_learnings' => $learnings]);

                return ['ok' => true, 'message' => 'Подсказка добавлена в черновик обучения.'];
            }
        }

        $this->pushDialogLearningToCache($user, $entry);

        return ['ok' => true, 'message' => 'Подсказка сохранена до создания черновика заявки.'];
    }

    public function activateDraft(User $user, int $draftId): void
    {
        if (! $this->userOwnsDraft($user, $draftId)) {
            return;
        }

        $this->rememberActiveDraft($user, $draftId);
    }

    /**
     * @param  array<string, mixed>  $appliedSnapshot
     */
    public function commit(User $user, int $draftId, int $orderId, array $appliedSnapshot): bool
    {
        if (! $this->tableAvailable()) {
            return false;
        }

        $record = OrderIntakeGoldenRecord::query()
            ->where('order_intake_draft_id', $draftId)
            ->where('user_id', $user->id)
            ->where('status', OrderIntakeGoldenRecordStatus::Pending)
            ->first();

        if ($record === null) {
            return false;
        }

        $record->update([
            'status' => OrderIntakeGoldenRecordStatus::Committed,
            'order_id' => $orderId,
            'applied_snapshot' => $this->sanitizeSnapshot($appliedSnapshot),
            'committed_at' => now(),
        ]);

        OrderIntakeDraft::query()
            ->whereKey($draftId)
            ->where('user_id', $user->id)
            ->update(['order_id' => $orderId]);

        $this->forgetActiveDraft($user);

        return true;
    }

    public function discard(User $user, int $draftId): bool
    {
        if (! $this->tableAvailable()) {
            return false;
        }

        $deleted = OrderIntakeGoldenRecord::query()
            ->where('order_intake_draft_id', $draftId)
            ->where('user_id', $user->id)
            ->where('status', OrderIntakeGoldenRecordStatus::Pending)
            ->delete();

        if ($this->activeDraftIdForUser($user) === $draftId) {
            $this->forgetActiveDraft($user);
        }

        return $deleted > 0;
    }

    private function discardOtherPendingForUser(User $user, int $exceptDraftId): void
    {
        $pendingDraftIds = OrderIntakeGoldenRecord::query()
            ->where('user_id', $user->id)
            ->where('status', OrderIntakeGoldenRecordStatus::Pending)
            ->where('order_intake_draft_id', '!=', $exceptDraftId)
            ->pluck('order_intake_draft_id')
            ->all();

        foreach ($pendingDraftIds as $pendingDraftId) {
            $this->discard($user, (int) $pendingDraftId);
        }
    }

    private function userOwnsDraft(User $user, int $draftId): bool
    {
        if (! Schema::hasTable('order_intake_drafts')) {
            return false;
        }

        return OrderIntakeDraft::query()
            ->whereKey($draftId)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function sanitizeInstruction(?string $instruction): string
    {
        if ($instruction === null || trim($instruction) === '') {
            return '';
        }

        $max = max(500, (int) config('ai.order_intake.golden_instruction_max_chars', 12000));
        $text = trim($instruction);

        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max);
        }

        return $this->sanitizer->sanitizeText($text, 'command_bar');
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function sanitizeSnapshot(array $snapshot): array
    {
        return $this->sanitizer->sanitizeStructured($snapshot, 'command_bar');
    }

    /**
     * @return list<array{source_phrase: string, canonical_value: string, field: string, recorded_at: string}>
     */
    private function pullDialogLearningsFromCache(User $user): array
    {
        $key = self::DIALOG_CACHE_PREFIX.$user->id;
        /** @var list<array{source_phrase: string, canonical_value: string, field: string, recorded_at: string}> $learnings */
        $learnings = Cache::pull($key, []);

        return is_array($learnings) ? $learnings : [];
    }

    /**
     * @param  array{source_phrase: string, canonical_value: string, field: string, recorded_at: string}  $entry
     */
    private function pushDialogLearningToCache(User $user, array $entry): void
    {
        $key = self::DIALOG_CACHE_PREFIX.$user->id;
        /** @var list<array{source_phrase: string, canonical_value: string, field: string, recorded_at: string}> $learnings */
        $learnings = Cache::get($key, []);

        if (! is_array($learnings)) {
            $learnings = [];
        }

        $learnings[] = $entry;
        Cache::put($key, $learnings, self::CACHE_TTL_SECONDS);
    }

    private function rememberActiveDraft(User $user, int $draftId): void
    {
        Cache::put(self::ACTIVE_DRAFT_CACHE_PREFIX.$user->id, $draftId, self::CACHE_TTL_SECONDS);
    }

    private function activeDraftIdForUser(User $user): ?int
    {
        $id = Cache::get(self::ACTIVE_DRAFT_CACHE_PREFIX.$user->id);

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    private function forgetActiveDraft(User $user): void
    {
        Cache::forget(self::ACTIVE_DRAFT_CACHE_PREFIX.$user->id);
    }
}

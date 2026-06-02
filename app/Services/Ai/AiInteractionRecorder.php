<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use App\Support\AiChannel;
use App\Support\AiInteractionEventType;
use App\Support\AiInteractionFeature;
use App\Support\AiInteractionOutcome;
use App\Support\AiInteractionPromptFingerprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AiInteractionRecorder
{
    public function __construct(
        private readonly ExternalLlmPayloadSanitizer $sanitizer,
    ) {}

    /**
     * @param  list<string>  $toolsUsed
     * @param  array<string, mixed>  $metadata
     */
    public function recordConversationTurn(
        User $user,
        AiInteractionFeature $feature,
        AiChannel|string|null $channel,
        AiInteractionOutcome $outcome,
        string $userPrompt,
        string $assistantReply,
        int $toolRounds = 0,
        array $toolsUsed = [],
        ?int $durationMs = null,
        ?int $tokensPrompt = null,
        ?int $tokensCompletion = null,
        ?string $errorMessage = null,
        array $metadata = [],
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $sanitizerProfile = $feature === AiInteractionFeature::Trainer ? 'trainer' : 'command_bar';
        $promptRedacted = $this->truncate(
            $this->sanitizer->sanitizeText($userPrompt, $sanitizerProfile),
            (int) config('ai.analytics.max_prompt_storage_chars', 2000),
        );
        $replyRedacted = $this->truncate(
            $this->sanitizer->sanitizeText($assistantReply, $sanitizerProfile),
            (int) config('ai.analytics.max_reply_storage_chars', 4000),
        );

        $this->insert([
            'user_id' => $user->id,
            'feature' => $feature->value,
            'event_type' => AiInteractionEventType::ConversationTurn->value,
            'channel' => $this->channelValue($channel),
            'outcome' => $outcome->value,
            'ok' => ! in_array($outcome, [AiInteractionOutcome::Failed, AiInteractionOutcome::Unavailable], true),
            'prompt_fingerprint' => AiInteractionPromptFingerprint::fromText($promptRedacted),
            'user_prompt_redacted' => $promptRedacted !== '' ? $promptRedacted : null,
            'assistant_reply_redacted' => $replyRedacted !== '' ? $replyRedacted : null,
            'tools_used' => $toolsUsed !== [] ? json_encode(array_values(array_unique($toolsUsed)), JSON_UNESCAPED_UNICODE) : null,
            'tool_rounds' => $toolRounds > 0 ? $toolRounds : null,
            'duration_ms' => $durationMs,
            'tokens_prompt' => $tokensPrompt,
            'tokens_completion' => $tokensCompletion,
            'error_message' => $this->truncate($errorMessage ?? '', 500) ?: null,
            'metadata' => $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $metadata
     */
    public function recordToolInvoked(
        User $user,
        AiInteractionFeature $feature,
        string $toolName,
        array $arguments,
        bool $ok,
        ?string $errorMessage = null,
        ?int $durationMs = null,
        array $metadata = [],
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $this->insert([
            'user_id' => $user->id,
            'feature' => $feature->value,
            'event_type' => AiInteractionEventType::ToolInvoked->value,
            'channel' => null,
            'outcome' => $ok ? AiInteractionOutcome::Success->value : AiInteractionOutcome::Failed->value,
            'ok' => $ok,
            'tool_name' => $toolName,
            'error_message' => $this->truncate($errorMessage ?? '', 500) ?: null,
            'duration_ms' => $durationMs,
            'metadata' => json_encode([
                'arguments' => $this->redactArguments($arguments),
                ...$metadata,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordIntakeExtracted(
        User $user,
        bool $ok,
        ?float $confidence,
        ?int $draftId,
        ?string $errorMessage = null,
        ?int $durationMs = null,
        array $metadata = [],
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $outcome = $ok
            ? ($confidence !== null && $confidence < 0.5 ? AiInteractionOutcome::WeakAnswer : AiInteractionOutcome::Success)
            : AiInteractionOutcome::Failed;

        $this->insert([
            'user_id' => $user->id,
            'feature' => AiInteractionFeature::OrderIntake->value,
            'event_type' => AiInteractionEventType::IntakeExtracted->value,
            'channel' => AiChannel::ExternalLarge->value,
            'outcome' => $outcome->value,
            'ok' => $ok,
            'duration_ms' => $durationMs,
            'error_message' => $this->truncate($errorMessage ?? '', 500) ?: null,
            'metadata' => json_encode([
                'draft_id' => $draftId,
                'confidence' => $confidence,
                ...$metadata,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function findConversationTurnMetadata(string $turnId): array
    {
        if (! Schema::hasTable('ai_interaction_events') || $turnId === '') {
            return [];
        }

        $row = DB::table('ai_interaction_events')
            ->where('event_type', AiInteractionEventType::ConversationTurn->value)
            ->where('metadata->turn_id', $turnId)
            ->orderByDesc('id')
            ->first(['metadata', 'prompt_fingerprint', 'user_prompt_redacted']);

        if ($row === null) {
            return [];
        }

        $metadata = json_decode((string) ($row->metadata ?? ''), true);

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $metadata['prompt_fingerprint'] = $row->prompt_fingerprint;
        $metadata['user_prompt_redacted'] = $row->user_prompt_redacted;

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordUserFeedback(
        User $user,
        AiInteractionFeature $feature,
        string $turnId,
        string $rating,
        ?string $comment = null,
        array $metadata = [],
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $this->insert([
            'user_id' => $user->id,
            'feature' => $feature->value,
            'event_type' => AiInteractionEventType::UserFeedback->value,
            'channel' => null,
            'outcome' => $rating === 'helpful'
                ? AiInteractionOutcome::Success->value
                : AiInteractionOutcome::WeakAnswer->value,
            'ok' => true,
            'metadata' => json_encode([
                'turn_id' => $turnId,
                'rating' => $rating,
                'comment' => $comment !== null && trim($comment) !== ''
                    ? $this->truncate($this->sanitizer->sanitizeText(trim($comment), 'command_bar'), 500)
                    : null,
                ...$metadata,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function insert(array $row): void
    {
        if (! Schema::hasTable('ai_interaction_events')) {
            return;
        }

        try {
            DB::table('ai_interaction_events')->insert([
                ...$row,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            // Аналитика не должна ломать основной сценарий.
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function redactArguments(array $arguments): array
    {
        $redacted = $arguments;

        foreach (['password', 'token', 'api_key', 'secret'] as $key) {
            if (array_key_exists($key, $redacted)) {
                $redacted[$key] = '[redacted]';
            }
        }

        return $redacted;
    }

    private function channelValue(AiChannel|string|null $channel): ?string
    {
        if ($channel instanceof AiChannel) {
            return $channel->value;
        }

        return is_string($channel) && $channel !== '' ? $channel : null;
    }

    private function truncate(string $text, int $max): string
    {
        if ($max <= 0 || mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max);
    }

    private function isEnabled(): bool
    {
        return (bool) config('ai.analytics.enabled', true);
    }
}

<?php

namespace App\Services\Commercial;

use App\Models\CommercialAiSuggestionLog;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommercialAiSuggestionLogService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function log(
        User $user,
        string $suggestionType,
        array $payload,
        ?int $mailThreadId = null,
        ?int $leadId = null,
    ): string {
        $suggestionKey = (string) Str::uuid();

        CommercialAiSuggestionLog::query()->create([
            'suggestion_key' => $suggestionKey,
            'user_id' => $user->id,
            'suggestion_type' => $suggestionType,
            'mail_thread_id' => $mailThreadId,
            'lead_id' => $leadId,
            'payload' => $this->truncatePayload($payload),
        ]);

        return $suggestionKey;
    }

    /**
     * @return array{ok: bool, suggestion_key: string}
     */
    public function recordFeedback(User $user, string $suggestionKey, string $rating, ?string $comment = null): array
    {
        $log = CommercialAiSuggestionLog::query()
            ->where('suggestion_key', $suggestionKey)
            ->where('user_id', $user->id)
            ->first();

        if ($log === null) {
            throw ValidationException::withMessages([
                'suggestion_key' => 'Подсказка не найдена или принадлежит другому пользователю.',
            ]);
        }

        if ($log->rated_at !== null) {
            throw ValidationException::withMessages([
                'suggestion_key' => 'Обратная связь по этой подсказке уже сохранена.',
            ]);
        }

        $log->forceFill([
            'rating' => $rating,
            'comment' => filled($comment) ? trim($comment) : null,
            'rated_at' => now(),
        ])->save();

        return [
            'ok' => true,
            'suggestion_key' => $suggestionKey,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function truncatePayload(array $payload): array
    {
        unset($payload['suggestion_key']);

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if (! is_string($encoded) || strlen($encoded) <= 8000) {
            return $payload;
        }

        return [
            'truncated' => true,
            'thread_id' => $payload['thread_id'] ?? null,
            'lead_id' => $payload['lead_id'] ?? null,
            'subject' => isset($payload['subject']) ? Str::limit((string) $payload['subject'], 200) : null,
        ];
    }
}

<?php

namespace App\Services\Commercial;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\Lead;
use App\Models\MailThread;
use App\Models\User;
use App\Services\Agents\AiRequestGate;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use App\Services\Mcp\MailMcpService;
use App\Services\Mcp\McpAccessGate;
use App\Support\AiChannel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MailThreadAnalysisService
{
    /** @var list<string> */
    private const TONES = ['neutral', 'friendly', 'formal', 'assertive'];

    public function __construct(
        private readonly MailMcpService $mail,
        private readonly McpAccessGate $access,
        private readonly ChatCompletionClient $chat,
        private readonly ExternalLlmPayloadSanitizer $sanitizer,
        private readonly AiRequestGate $aiGate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summarizeThread(User $user, int $threadId, int $messageLimit = 20): array
    {
        $payload = $this->mail->getThread($user, $threadId, $messageLimit);
        $this->assertAiAvailable($user);

        $thread = $payload['thread'] ?? [];
        $messages = is_array($payload['messages'] ?? null) ? $payload['messages'] : [];
        $transcript = $this->buildTranscript($messages);

        if ($transcript === '') {
            throw ValidationException::withMessages([
                'thread_id' => 'В цепочке нет текста для анализа.',
            ]);
        }

        $systemPrompt = <<<'TEXT'
Ты помощник менеджера экспедиторской компании «Автоальянс». По переписке составь краткое резюме на русском.

Правила:
- Не цитируй сырые персональные данные (email, телефоны) — используй роли («клиент», «мы»).
- Если тело письма удалено по политике хранения — опирайся только на retention summary.
- Ответ — только JSON без markdown:
{"summary":"2-4 предложения","key_points":["..."],"open_questions":["..."],"participants":[{"direction":"inbound|outbound","label":"кратко кто","last_at":"ISO8601|null"}]}
TEXT;

        $parsed = $this->chatJson(
            $systemPrompt,
            'Тема: '.(string) ($thread['subject'] ?? '(без темы)')."\n\nПереписка:\n".$transcript,
        );

        return [
            'thread_id' => (int) ($thread['thread_id'] ?? $threadId),
            'subject' => $thread['subject'] ?? null,
            'lead_id' => $thread['lead_id'] ?? null,
            'contractor_id' => $thread['contractor_id'] ?? null,
            'date_range' => $this->messageDateRange($messages),
            'summary' => (string) ($parsed['summary'] ?? ''),
            'key_points' => $this->stringList($parsed['key_points'] ?? []),
            'open_questions' => $this->stringList($parsed['open_questions'] ?? []),
            'participants' => is_array($parsed['participants'] ?? null) ? $parsed['participants'] : [],
            'messages_analyzed' => count($messages),
            'note' => 'Резюме для менеджера. Отправка писем — только через reply_mail_thread / send_mail по явной просьбе.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function draftReply(User $user, int $threadId, string $tone = 'neutral', int $messageLimit = 20): array
    {
        $tone = $this->normalizeTone($tone);
        $payload = $this->mail->getThread($user, $threadId, $messageLimit);
        $this->assertAiAvailable($user);

        $thread = $payload['thread'] ?? [];
        $messages = is_array($payload['messages'] ?? null) ? $payload['messages'] : [];
        $transcript = $this->buildTranscript($messages);

        if ($transcript === '') {
            throw ValidationException::withMessages([
                'thread_id' => 'В цепочке нет текста для черновика ответа.',
            ]);
        }

        $systemPrompt = <<<TEXT
Ты помощник менеджера экспедиторской компании. Составь черновик ответа клиенту на русском.
Тон: {$tone} (neutral — нейтрально-деловой, friendly — дружелюбно, formal — официально, assertive — уверенно по сути).

Правила:
- Не отправляй письмо — только черновик для проверки менеджером.
- Не выдумывай ставки, сроки и обязательства, которых нет в переписке.
- Без подписи с ФИО, если она неизвестна из контекста.
- Ответ — только JSON: {"subject":"Re: ...","body":"текст письма"}
TEXT;

        $parsed = $this->chatJson(
            $systemPrompt,
            'Тема: '.(string) ($thread['subject'] ?? '(без темы)')."\n\nПереписка:\n".$transcript,
        );

        $subject = trim((string) ($parsed['subject'] ?? ''));
        $body = trim((string) ($parsed['body'] ?? ''));

        if ($subject === '' || $body === '') {
            throw new RuntimeException('Модель не вернула subject/body для черновика.');
        }

        return [
            'thread_id' => (int) ($thread['thread_id'] ?? $threadId),
            'tone' => $tone,
            'subject' => $subject,
            'body' => $body,
            'disclaimer' => 'Черновик — проверьте перед отправкой. Для отправки используйте reply_mail_thread.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function suggestLeadNextStep(User $user, int $leadId, ?int $threadId = null): array
    {
        $this->access->requireMailArea($user);
        $lead = $this->access->findAccessibleLead($user, $leadId);
        $this->assertAiAvailable($user);

        $mailContext = '';

        if ($threadId !== null && $threadId > 0) {
            $payload = $this->mail->getThread($user, $threadId, 15);
            $messages = is_array($payload['messages'] ?? null) ? $payload['messages'] : [];
            $transcript = $this->buildTranscript($messages);

            if ($transcript !== '') {
                $subject = (string) (($payload['thread']['subject'] ?? '') ?: '(без темы)');
                $mailContext = "Переписка (thread_id {$threadId}, тема: {$subject}):\n".$transcript;
            }
        } elseif (Schema::hasTable('mail_threads')) {
            $linkedThread = MailThread::query()
                ->where('lead_id', $lead->id)
                ->orderByDesc('last_message_at')
                ->first();

            if ($linkedThread !== null) {
                $payload = $this->mail->getThread($user, (int) $linkedThread->id, 10);
                $messages = is_array($payload['messages'] ?? null) ? $payload['messages'] : [];
                $transcript = $this->buildTranscript($messages);

                if ($transcript !== '') {
                    $mailContext = "Последняя переписка по лиду (thread_id {$linkedThread->id}):\n".$transcript;
                    $threadId = (int) $linkedThread->id;
                }
            }
        }

        $leadContext = $this->serializeLeadContext($lead);

        $systemPrompt = <<<'TEXT'
Ты коуч менеджера по продажам в экспедиторской компании. Предложи следующий шаг по лиду с учётом переписки (если есть).

Ответ — только JSON:
{"next_step":"конкретное действие","rationale":"почему","suggested_task_title":"заголовок задачи в CRM","urgency":"high|medium|low"}

Не предлагай автоматическую отправку письма — только шаг и при необходимости черновик через отдельный tool.
TEXT;

        $userPrompt = "Контекст лида:\n".$leadContext;

        if ($mailContext !== '') {
            $userPrompt .= "\n\n".$mailContext;
        } else {
            $userPrompt .= "\n\nПереписка не приложена или пуста — опирайся на карточку лида.";
        }

        $parsed = $this->chatJson($systemPrompt, $userPrompt);

        $urgency = (string) ($parsed['urgency'] ?? 'medium');

        if (! in_array($urgency, ['high', 'medium', 'low'], true)) {
            $urgency = 'medium';
        }

        return [
            'lead_id' => $lead->id,
            'thread_id' => $threadId,
            'next_step' => trim((string) ($parsed['next_step'] ?? '')),
            'rationale' => trim((string) ($parsed['rationale'] ?? '')),
            'suggested_task_title' => trim((string) ($parsed['suggested_task_title'] ?? '')),
            'urgency' => $urgency,
            'note' => 'Рекомендация для менеджера — не создаёт задачу автоматически.',
        ];
    }

    private function assertAiAvailable(User $user): void
    {
        if ($this->aiGate->channelFor('command_bar', $user) === AiChannel::LocalOnly) {
            throw ValidationException::withMessages([
                'thread_id' => $this->aiGate->unavailableMessage('command_bar'),
            ]);
        }
    }

    private function normalizeTone(string $tone): string
    {
        $normalized = strtolower(trim($tone));

        return in_array($normalized, self::TONES, true) ? $normalized : 'neutral';
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    private function buildTranscript(array $messages): string
    {
        $lines = [];

        foreach (array_reverse($messages) as $message) {
            if (! is_array($message)) {
                continue;
            }

            $body = trim((string) ($message['body_text'] ?? ''));

            if ($body === '') {
                continue;
            }

            $direction = (string) ($message['direction'] ?? 'unknown');
            $sentAt = (string) ($message['sent_at'] ?? '');
            $purged = (bool) ($message['body_purged'] ?? false);
            $prefix = $purged ? '[retention]' : "[{$direction}]";

            $lines[] = trim("{$prefix} {$sentAt}\n{$body}");
        }

        return implode("\n\n---\n\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array{first: string|null, last: string|null}
     */
    private function messageDateRange(array $messages): array
    {
        $dates = collect($messages)
            ->pluck('sent_at')
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->sort()
            ->values();

        return [
            'first' => $dates->first(),
            'last' => $dates->last(),
        ];
    }

    private function serializeLeadContext(Lead $lead): string
    {
        $qualification = is_array($lead->lead_qualification) ? $lead->lead_qualification : [];

        $lines = [
            "lead_id: {$lead->id}",
            'number: '.($lead->number ?? '—'),
            'status: '.($lead->status ?? '—'),
            'title: '.($lead->title ?? '—'),
            'next_contact_at: '.optional($lead->next_contact_at)?->toIso8601String(),
            'qualification: '.json_encode($qualification, JSON_UNESCAPED_UNICODE),
        ];

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function chatJson(string $systemPrompt, string $userPrompt): array
    {
        $messages = $this->sanitizer->sanitizeMessages([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], 'command_bar');

        $content = trim($this->chat->chat($messages, [
            'temperature' => (float) config('ai.mail_analysis.temperature', 0.3),
            'max_tokens' => (int) config('ai.mail_analysis.max_tokens', 1200),
        ]));

        return $this->parseJsonObject($content);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonObject(string $content): array
    {
        $trimmed = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/u', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        throw new RuntimeException('Не удалось разобрать JSON-ответ модели.');
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => trim($item))
            ->values()
            ->all();
    }
}

<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Support\LeadStatus;
use Illuminate\Support\Facades\DB;

class LeadMessageIntakeService
{
    /**
     * @return array{lead: Lead, parsed: array<string, mixed>}
     */
    public function createFromText(string $message, ?User $user = null): array
    {
        $parsed = $this->parse($message);

        $lead = Lead::query()->create([
            'number' => $this->nextLeadNumber(),
            'status' => LeadStatus::values()[0],
            'source' => 'traklo_message_intake',
            'responsible_id' => $user?->id,
            'title' => $this->title($parsed),
            'description' => $this->description($message, $parsed),
            'loading_location' => $parsed['loading_location'],
            'unloading_location' => $parsed['unloading_location'],
            'lead_qualification' => [
                'need' => 'Заявка из текста сообщения',
                'timeline' => null,
            ],
            'metadata' => [
                'traklo_message_intake' => [
                    'raw_text' => $message,
                    'contact_phone' => $parsed['phone'],
                    'cargo' => $parsed['cargo'],
                    'created_from_user_id' => $user?->id,
                    'submitted_at' => now()->toIso8601String(),
                ],
            ],
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return [
            'lead' => $lead,
            'parsed' => $parsed,
        ];
    }

    /**
     * @return array{loading_location: string|null, unloading_location: string|null, cargo: string|null, phone: string|null}
     */
    public function parse(string $message): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);

        return [
            'loading_location' => $this->matchFirst($text, [
                '/\bиз\s+(.+?)\s+\bв\s+/iu',
                '/\bоткуда[:\s]+(.+?)(?:\s+куда[:\s]+|$)/iu',
                '/\bпогрузк[аи][:\s]+(.+?)(?:\s+выгрузк[аи][:\s]+|$)/iu',
            ]),
            'unloading_location' => $this->matchFirst($text, [
                '/\bиз\s+.+?\s+\bв\s+(.+?)(?:[,.]\s*груз\b|\s+груз\b|\s+машин[ау]\b|\s+тел\b|$)/iu',
                '/\bкуда[:\s]+(.+?)(?:\s+груз[:\s]+|$)/iu',
                '/\bвыгрузк[аи][:\s]+(.+?)(?:\s+груз[:\s]+|$)/iu',
            ]),
            'cargo' => $this->matchFirst($text, [
                '/\bгруз[:\s]+(.+?)(?:[,.]?\s+тел(?:ефон)?[:\s]+|[,.]?\s+контакт[:\s]+|$)/iu',
                '/\bгруз\s+(.+?)(?:[,.]?\s+тел(?:ефон)?[:\s]+|[,.]?\s+контакт[:\s]+|$)/iu',
            ]),
            'phone' => $this->matchPhone($text),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function title(array $parsed): string
    {
        $loading = $parsed['loading_location'] ?: 'откуда не указано';
        $unloading = $parsed['unloading_location'] ?: 'куда не указано';

        return sprintf('Заявка из сообщения: %s → %s', $loading, $unloading);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function description(string $message, array $parsed): string
    {
        $lines = [
            'Заявка создана из вставленного сообщения Traklo.',
            'Маршрут: '.($parsed['loading_location'] ?: 'не распознан').' → '.($parsed['unloading_location'] ?: 'не распознана'),
            'Груз: '.($parsed['cargo'] ?: 'не распознан'),
            'Телефон: '.($parsed['phone'] ?: 'не распознан'),
            '',
            'Исходный текст:',
            $message,
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchFirst(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return $this->cleanMatch($matches[1] ?? null);
            }
        }

        return null;
    }

    private function matchPhone(string $text): ?string
    {
        if (preg_match('/(?:\+7|8)[\s(.-]*\d{3}[\s).-]*\d{3}[\s.-]*\d{2}[\s.-]*\d{2}/u', $text, $matches) !== 1) {
            return null;
        }

        return trim($matches[0]);
    }

    private function cleanMatch(?string $value): ?string
    {
        $cleaned = trim((string) $value, " \t\n\r\0\x0B.,;:-");

        return $cleaned === '' ? null : mb_substr($cleaned, 0, 255);
    }

    private function nextLeadNumber(): string
    {
        $prefix = 'LD-'.now()->format('ymd');
        $sequence = DB::table('leads')
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }
}

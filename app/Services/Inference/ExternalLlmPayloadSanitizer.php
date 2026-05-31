<?php

namespace App\Services\Inference;

/**
 * Обезличивание payload перед отправкой во внешний LLM (уровень 3).
 * Профили задают глубину redaction по сценарию (command bar vs тренажёр).
 */
class ExternalLlmPayloadSanitizer
{
    private const string REDACTED = '[redacted]';

    private const string REDACTED_ID = '[redacted_id]';

    /** @var list<string> */
    private const SENSITIVE_FIELD_KEYS = [
        'email',
        'phone',
        'mobile',
        'inn',
        'kpp',
        'ogrn',
        'address',
        'address_line',
        'customer_contact_name',
        'customer_contact_phone',
        'customer_contact_email',
        'signer_name',
        'signer_position',
        'passport',
        'password',
        'token',
        'api_key',
    ];

    public function isEnabled(): bool
    {
        return (bool) config('ai.sanitizer.enabled', true);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>>
     */
    public function sanitizeMessages(array $messages, string $profile = 'default'): array
    {
        if (! $this->isEnabled()) {
            return $messages;
        }

        $sanitized = [];

        foreach ($messages as $message) {
            $sanitized[] = $this->sanitizeMessage($message, $profile);
        }

        return $sanitized;
    }

    public function sanitizeText(string $text, string $profile = 'default'): string
    {
        if (! $this->isEnabled() || $text === '') {
            return $text;
        }

        $options = $this->profileOptions($profile);

        if ($options['redact_pii_patterns']) {
            $text = $this->redactEmailPatterns($text);
            $text = $this->redactPhonePatterns($text);
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizeStructured(mixed $payload, string $profile = 'default'): array
    {
        if (! $this->isEnabled()) {
            return is_array($payload) ? $payload : [];
        }

        $normalized = $this->normalizeToArray($payload);

        return $this->sanitizeArray($normalized, $profile, '');
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function sanitizeMessage(array $message, string $profile): array
    {
        $role = (string) ($message['role'] ?? '');

        if ($role === 'tool' && isset($message['content']) && is_string($message['content'])) {
            $decoded = json_decode($message['content'], true);
            if (is_array($decoded)) {
                $message['content'] = json_encode(
                    $this->sanitizeStructured($decoded, $profile),
                    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
                );
            } else {
                $message['content'] = $this->sanitizeText($message['content'], $profile);
            }

            return $message;
        }

        if (isset($message['content']) && is_string($message['content'])) {
            $message['content'] = $this->sanitizeText($message['content'], $profile);
        }

        if (isset($message['tool_calls']) && is_array($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $index => $toolCall) {
                if (! is_array($toolCall)) {
                    continue;
                }

                $function = $toolCall['function'] ?? null;
                if (! is_array($function) || ! isset($function['arguments']) || ! is_string($function['arguments'])) {
                    continue;
                }

                $decodedArgs = json_decode($function['arguments'], true);
                if (is_array($decodedArgs)) {
                    $toolCall['function']['arguments'] = json_encode(
                        $this->sanitizeStructured($decodedArgs, $profile),
                        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
                    );
                }

                $message['tool_calls'][$index] = $toolCall;
            }
        }

        return $message;
    }

    /**
     * @return array{redact_pii_patterns: bool, redact_entity_ids: bool, redact_sensitive_fields: bool}
     */
    private function profileOptions(string $profile): array
    {
        /** @var array<string, array<string, bool>> $profiles */
        $profiles = config('ai.sanitizer.profiles', []);
        $defaults = [
            'redact_pii_patterns' => true,
            'redact_entity_ids' => false,
            'redact_sensitive_fields' => true,
        ];

        return array_merge($defaults, $profiles[$profile] ?? $profiles['default'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $data, string $profile, string $parentKey): array
    {
        $options = $this->profileOptions($profile);
        $result = [];

        foreach ($data as $key => $value) {
            $stringKey = (string) $key;

            if ($options['redact_sensitive_fields'] && $this->isSensitiveFieldKey($stringKey)) {
                $result[$stringKey] = self::REDACTED;

                continue;
            }

            if ($options['redact_entity_ids'] && $this->isEntityIdField($stringKey) && $this->isScalarId($value)) {
                $result[$stringKey] = self::REDACTED_ID;

                continue;
            }

            if (is_array($value)) {
                $result[$stringKey] = $this->sanitizeArray($value, $profile, $stringKey);

                continue;
            }

            if (is_string($value)) {
                $result[$stringKey] = $this->sanitizeText($value, $profile);

                continue;
            }

            $result[$stringKey] = $value;
        }

        return $result;
    }

    private function isSensitiveFieldKey(string $key): bool
    {
        $normalized = mb_strtolower($key);

        foreach (self::SENSITIVE_FIELD_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_ends_with($normalized, '_'.$sensitiveKey)) {
                return true;
            }
        }

        return str_contains($normalized, 'password')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'passport');
    }

    private function isEntityIdField(string $key): bool
    {
        $normalized = mb_strtolower($key);

        if ($normalized === 'id') {
            return true;
        }

        return str_ends_with($normalized, '_id');
    }

    private function isScalarId(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value > 0;
        }

        return false;
    }

    private function redactEmailPatterns(string $text): string
    {
        return (string) preg_replace(
            '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u',
            self::REDACTED,
            $text,
        );
    }

    private function redactPhonePatterns(string $text): string
    {
        $patterns = [
            '/(?:\+7|8)[\s\-]?\(?\d{3}\)?[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}/u',
            '/\b\d{3}[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}\b/u',
        ];

        foreach ($patterns as $pattern) {
            $text = (string) preg_replace($pattern, self::REDACTED, $text);
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeToArray(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return $payload;
    }
}

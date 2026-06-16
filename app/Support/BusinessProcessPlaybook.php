<?php

namespace App\Support;

/**
 * Playbook этапа бизнес-процесса: markdown, плейсхолдеры, нормализация для задач.
 */
final class BusinessProcessPlaybook
{
    public const MAX_MARKDOWN_LENGTH = 65535;

    /**
     * @return list<array{token: string, label: string, hint: string}>
     */
    public static function placeholderCatalog(): array
    {
        return [
            ['token' => '{lead_number}', 'label' => 'Номер лида', 'hint' => 'Уникальный номер заявки'],
            ['token' => '{lead_title}', 'label' => 'Название лида', 'hint' => 'Краткое описание сделки'],
            ['token' => '{stage_name}', 'label' => 'Этап', 'hint' => 'Текущий этап процесса'],
            ['token' => '{process_name}', 'label' => 'Процесс', 'hint' => 'Название воронки'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function placeholderTokens(): array
    {
        return array_column(self::placeholderCatalog(), 'token');
    }

    public static function normalize(?string $markdown): ?string
    {
        if ($markdown === null) {
            return null;
        }

        $markdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));

        if ($markdown === '') {
            return null;
        }

        if (mb_strlen($markdown) > self::MAX_MARKDOWN_LENGTH) {
            $markdown = mb_substr($markdown, 0, self::MAX_MARKDOWN_LENGTH);
        }

        return $markdown;
    }

    /**
     * Markdown → plain text для автозадач и уведомлений.
     */
    public static function toPlainText(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        $text = $markdown;
        $text = preg_replace('/```[\s\S]*?```/m', '', $text) ?? $text;
        $text = preg_replace('/`([^`]+)`/', '$1', $text) ?? $text;
        $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/', '', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s+/m', '', $text) ?? $text;
        $text = preg_replace('/^>\s?/m', '', $text) ?? $text;
        $text = preg_replace('/^[-*+]\s+\[[ xX]\]\s+/m', '• ', $text) ?? $text;
        $text = preg_replace('/^[-*+]\s+/m', '• ', $text) ?? $text;
        $text = preg_replace('/^\d+\.\s+/m', '• ', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text) ?? $text;
        $text = preg_replace('/\*([^*]+)\*/', '$1', $text) ?? $text;
        $text = preg_replace('/__([^_]+)__/', '$1', $text) ?? $text;
        $text = preg_replace('/_([^_]+)_/', '$1', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Шаблон playbook для пустого этапа (методика «цель → действия → критерий готовности»).
     */
    public static function emptyPlaybookTemplate(string $stageName): string
    {
        return <<<MD
## Что делаем на этапе «{$stageName}»

- [ ] Первый шаг менеджера
- [ ] Второй шаг

**Подсказка:** опишите конкретные действия, а не общие слова.
MD;
    }

    public static function emptySuccessCriteriaTemplate(): string
    {
        return <<<'MD'
Этап можно считать завершённым, когда:

- [ ] Критерий 1 (измеримый результат)
- [ ] Критерий 2
MD;
    }
}

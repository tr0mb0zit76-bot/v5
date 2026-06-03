<?php

namespace App\Support;

final class SalesBookQuizParser
{
    public const START_MARKER = '<!-- sales-book:quiz -->';

    public const END_MARKER = '<!-- /sales-book:quiz -->';

    /**
     * @return array{
     *     questions: list<array{
     *         id: string,
     *         text: string,
     *         options: list<array{id: string, text: string}>,
     *         correct: string,
     *         explanation: string|null
     *     }>
     * }|null
     */
    public function parse(string $content): ?array
    {
        $json = $this->extractJson($content);

        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! isset($decoded['questions']) || ! is_array($decoded['questions'])) {
            return null;
        }

        $questions = [];

        foreach ($decoded['questions'] as $index => $question) {
            if (! is_array($question)) {
                continue;
            }

            $parsed = $this->parseQuestion($question, (int) $index);

            if ($parsed !== null) {
                $questions[] = $parsed;
            }
        }

        if ($questions === []) {
            return null;
        }

        return ['questions' => $questions];
    }

    public function stripBlock(string $content): string
    {
        $pattern = '/\n?'.preg_quote(self::START_MARKER, '/').'.*?'.preg_quote(self::END_MARKER, '/').'/s';

        return trim((string) preg_replace($pattern, '', $content));
    }

    private function extractJson(string $content): ?string
    {
        $pattern = '/'.preg_quote(self::START_MARKER, '/').'\s*(.*?)\s*'.preg_quote(self::END_MARKER, '/').'/s';

        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        $json = trim($matches[1]);

        return $json !== '' ? $json : null;
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array{
     *     id: string,
     *     text: string,
     *     options: list<array{id: string, text: string}>,
     *     correct: string,
     *     explanation: string|null
     * }|null
     */
    private function parseQuestion(array $question, int $index): ?array
    {
        $text = trim((string) ($question['text'] ?? ''));

        if ($text === '') {
            return null;
        }

        $options = [];
        $rawOptions = $question['options'] ?? [];

        if (! is_array($rawOptions)) {
            return null;
        }

        foreach ($rawOptions as $optionIndex => $option) {
            if (! is_array($option)) {
                continue;
            }

            $optionId = trim((string) ($option['id'] ?? ''));
            $optionText = trim((string) ($option['text'] ?? ''));

            if ($optionId === '' || $optionText === '') {
                continue;
            }

            $options[] = [
                'id' => mb_substr($optionId, 0, 8),
                'text' => mb_substr($optionText, 0, 500),
            ];
        }

        if (count($options) < 2) {
            return null;
        }

        $correct = trim((string) ($question['correct'] ?? ''));

        if ($correct === '') {
            return null;
        }

        $optionIds = array_column($options, 'id');

        if (! in_array($correct, $optionIds, true)) {
            return null;
        }

        $id = trim((string) ($question['id'] ?? ''));

        if ($id === '') {
            $id = 'q'.($index + 1);
        }

        $explanation = trim((string) ($question['explanation'] ?? ''));

        return [
            'id' => mb_substr($id, 0, 32),
            'text' => mb_substr($text, 0, 2000),
            'options' => array_slice($options, 0, 6),
            'correct' => $correct,
            'explanation' => $explanation !== '' ? mb_substr($explanation, 0, 1000) : null,
        ];
    }
}

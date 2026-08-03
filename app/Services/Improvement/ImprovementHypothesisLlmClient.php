<?php

namespace App\Services\Improvement;

use App\Contracts\Inference\ChatCompletionClient;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use RuntimeException;
use Throwable;

/**
 * Четыре роли Propose на базе существующего ChatCompletionClient (не отдельный OpenAI SDK).
 */
final class ImprovementHypothesisLlmClient
{
    public function __construct(
        private readonly ChatCompletionClient $chat,
        private readonly ExternalLlmPayloadSanitizer $sanitizer,
    ) {}

    public function isAvailable(): bool
    {
        return $this->chat->isAvailable();
    }

    /**
     * @param  list<string>  $dialogSnippets
     * @return list<string>
     */
    public function gatherPains(array $dialogSnippets): array
    {
        $user = "Вот отказы и заметки по проигранным лидам:\n".implode("\n---\n", $dialogSnippets);
        $raw = $this->call(ImprovementHypothesisPrompts::ARCHAEOLOGIST, $user);
        $decoded = $this->decodeJsonObject($raw);
        $pains = $decoded['pains'] ?? [];

        if (! is_array($pains)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $p): string => is_string($p) ? trim($p) : '',
            $pains,
        ), fn (string $p): bool => $p !== ''));
    }

    /**
     * @param  list<string>  $pains
     * @return list<array{category: string, text: string, short_reason: string}>
     */
    public function generateIdeas(array $pains): array
    {
        $user = 'Список возражений клиентов: '.json_encode($pains, JSON_UNESCAPED_UNICODE);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $raw = $this->call(ImprovementHypothesisPrompts::STRATEGIST, $user, $attempt);
            $mapped = $this->mapIdeaRows($this->decodeJsonList($raw));
            if ($mapped !== []) {
                return $mapped;
            }
        }

        return [];
    }

    /**
     * @param  list<mixed>  $ideas
     * @return list<array{category: string, text: string, short_reason: string}>
     */
    private function mapIdeaRows(array $ideas): array
    {
        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') {
                return null;
            }

            return [
                'category' => $this->normalizeCategory((string) ($item['category'] ?? 'script')),
                'text' => mb_substr($text, 0, 500),
                'short_reason' => mb_substr(trim((string) ($item['short_reason'] ?? '')), 0, 200),
            ];
        }, $ideas)));
    }

    /**
     * @param  list<array{category: string, text: string, short_reason: string}>  $ideas
     * @return list<array{category: string, text: string, short_reason: string}>
     */
    public function validateIdeas(array $ideas): array
    {
        $user = 'Входящий список гипотез: '.json_encode($ideas, JSON_UNESCAPED_UNICODE);
        $raw = $this->call(ImprovementHypothesisPrompts::CRITIC, $user);
        $kept = $this->decodeJsonList($raw);

        $out = [];
        foreach ($kept as $item) {
            if (! is_array($item)) {
                continue;
            }
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $out[] = [
                'category' => $this->normalizeCategory((string) ($item['category'] ?? 'script')),
                'text' => $text,
                'short_reason' => trim((string) ($item['short_reason'] ?? '')),
            ];
            if (count($out) >= 5) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{category: string, text: string, short_reason: string}>  $ideas
     * @return list<array{category: string, text: string, short_reason: string, impact: int, confidence: int, ease: int, score: float}>
     */
    public function scoreIdeas(array $ideas): array
    {
        $user = 'Входящий список гипотез для оценки: '.json_encode($ideas, JSON_UNESCAPED_UNICODE);
        $raw = $this->call(ImprovementHypothesisPrompts::METRIC, $user);
        $decoded = $this->decodeJsonObject($raw);

        // Метрик может вернуть один объект или {hypotheses:[...]} / список.
        $rows = [];
        if (isset($decoded['text'])) {
            $rows = [$decoded];
        } elseif (isset($decoded['hypotheses']) && is_array($decoded['hypotheses'])) {
            $rows = $decoded['hypotheses'];
        } else {
            $list = $this->decodeJsonList($raw);
            $rows = $list !== [] ? $list : [$decoded];
        }

        $scored = [];
        foreach ($rows as $item) {
            if (! is_array($item)) {
                continue;
            }
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $impact = $this->clampScore($item['impact'] ?? 5);
            $confidence = $this->clampScore($item['confidence'] ?? 5);
            $ease = max(1, $this->clampScore($item['ease'] ?? 5));
            $score = isset($item['score']) && is_numeric($item['score'])
                ? round((float) $item['score'], 2)
                : round(($impact + $confidence) / $ease, 2);

            $scored[] = [
                'category' => $this->normalizeCategory((string) ($item['category'] ?? 'script')),
                'text' => $text,
                'short_reason' => trim((string) ($item['short_reason'] ?? '')),
                'impact' => $impact,
                'confidence' => $confidence,
                'ease' => $ease,
                'score' => $score,
            ];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $scored;
    }

    private function call(string $system, string $user, int $attempt = 1): string
    {
        $messages = $this->sanitizer->sanitizeMessages([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user.($attempt > 1 ? "\n\nВерни только JSON, без пояснений." : '')],
        ], 'command_bar');

        try {
            $content = trim($this->chat->chat($messages, [
                'temperature' => $attempt > 1 ? 0.1 : (float) config('ai.improvement_loop.temperature', 0.3),
                'max_tokens' => (int) config('ai.improvement_loop.max_tokens', 2000),
            ]));
        } catch (Throwable $e) {
            if ($attempt < 3) {
                return $this->call($system, $user, $attempt + 1);
            }
            throw $e;
        }

        if ($content === '' && $attempt < 3) {
            return $this->call($system, $user, $attempt + 1);
        }

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $raw): array
    {
        $json = $this->extractJson($raw);
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('LLM вернула невалидный JSON-объект.');
        }

        return $decoded;
    }

    /**
     * @return list<mixed>
     */
    private function decodeJsonList(string $raw): array
    {
        $json = $this->extractJson($raw);
        $decoded = json_decode($json, true);
        if (is_array($decoded) && array_is_list($decoded)) {
            return $decoded;
        }
        if (is_array($decoded)) {
            foreach (['ideas', 'hypotheses', 'items', 'pains'] as $key) {
                if (isset($decoded[$key]) && is_array($decoded[$key]) && array_is_list($decoded[$key])) {
                    return $decoded[$key];
                }
            }
        }

        // Обрезанный mid-JSON ответ: вытаскиваем только полные объекты с полем text.
        return $this->recoverIdeaObjectsFromTruncatedJson($raw);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recoverIdeaObjectsFromTruncatedJson(string $raw): array
    {
        if (! preg_match_all('/\{[^{}]*"text"\s*:\s*"(?:\\\\.|[^"\\\\])*"[^{}]*\}/u', $raw, $matches)) {
            return [];
        }

        $out = [];
        foreach ($matches[0] as $chunk) {
            $item = json_decode($chunk, true);
            if (is_array($item) && trim((string) ($item['text'] ?? '')) !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    private function extractJson(string $raw): string
    {
        $trim = trim($raw);
        if (str_starts_with($trim, '```')) {
            $trim = preg_replace('/^```(?:json)?\s*/i', '', $trim) ?? $trim;
            $trim = preg_replace('/\s*```$/', '', $trim) ?? $trim;
            $trim = trim($trim);
        }

        if ($trim === '') {
            return $trim;
        }

        if (str_starts_with($trim, '{') || str_starts_with($trim, '[')) {
            return $trim;
        }

        $obj = strpos($trim, '{');
        $arr = strpos($trim, '[');
        $startCandidates = array_values(array_filter([$obj, $arr], fn (int|false $p): bool => $p !== false));
        if ($startCandidates === []) {
            return $trim;
        }

        $start = min($startCandidates);

        return substr($trim, $start);
    }

    private function normalizeCategory(string $category): string
    {
        $c = mb_strtolower(trim($category));

        return match (true) {
            str_contains($c, 'цен') || $c === 'price' => 'price',
            str_contains($c, 'канал') || $c === 'channel' => 'channel',
            str_contains($c, 'процесс') || $c === 'process' => 'process',
            default => 'script',
        };
    }

    private function clampScore(mixed $value): int
    {
        $n = is_numeric($value) ? (int) $value : 5;

        return max(1, min(10, $n));
    }
}

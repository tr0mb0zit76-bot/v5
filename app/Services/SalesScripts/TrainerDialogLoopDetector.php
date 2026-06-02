<?php

namespace App\Services\SalesScripts;

use App\Models\SalesScriptTrainerMessage;
use Illuminate\Support\Collection;

/**
 * Эвристики «ход по кругу» в чате тренажёра.
 */
final class TrainerDialogLoopDetector
{
    /**
     * @param  Collection<int, SalesScriptTrainerMessage>  $messages
     * @return array{
     *     loop_detected: bool,
     *     severity: string|null,
     *     reasons: list<string>,
     *     repeated_assistant_snippet: string|null
     * }
     */
    public function analyze(Collection $messages): array
    {
        $assistantLines = $messages
            ->filter(fn (SalesScriptTrainerMessage $message): bool => $message->role === 'assistant')
            ->map(fn (SalesScriptTrainerMessage $message): string => trim($message->content))
            ->filter(fn (string $content): bool => $content !== '')
            ->values()
            ->all();

        $userLines = $messages
            ->filter(fn (SalesScriptTrainerMessage $message): bool => $message->role === 'user')
            ->map(fn (SalesScriptTrainerMessage $message): string => trim($message->content))
            ->filter(fn (string $content): bool => $content !== '')
            ->values()
            ->all();

        $reasons = [];
        $repeatedSnippet = null;

        if (count($assistantLines) >= 2) {
            $last = $assistantLines[count($assistantLines) - 1];
            $prev = $assistantLines[count($assistantLines) - 2];

            if ($this->similarityRatio($last, $prev) >= 0.72) {
                $reasons[] = 'assistant_repeated_reply';
                $repeatedSnippet = mb_substr($last, 0, 160);
            }
        }

        if (count($assistantLines) >= 3) {
            $recentNegative = $messages
                ->filter(fn (SalesScriptTrainerMessage $message): bool => $message->role === 'assistant')
                ->slice(-3)
                ->filter(fn (SalesScriptTrainerMessage $message): bool => ($message->auto_peer_reaction?->value ?? '') === 'negative')
                ->count();

            if ($recentNegative >= 2) {
                $reasons[] = 'assistant_quality_drop';
            }
        }

        if (count($userLines) >= 2) {
            $lastUser = $userLines[count($userLines) - 1];
            $prevUser = $userLines[count($userLines) - 2];

            if ($this->similarityRatio($lastUser, $prevUser) >= 0.68) {
                $reasons[] = 'user_repeated_question';
            }
        }

        $loopDetected = $reasons !== [];
        $severity = null;

        if (in_array('assistant_repeated_reply', $reasons, true) && in_array('user_repeated_question', $reasons, true)) {
            $severity = 'high';
        } elseif ($loopDetected) {
            $severity = 'medium';
        }

        return [
            'loop_detected' => $loopDetected,
            'severity' => $severity,
            'reasons' => $reasons,
            'repeated_assistant_snippet' => $repeatedSnippet,
        ];
    }

    private function similarityRatio(string $left, string $right): float
    {
        $leftNorm = $this->normalize($left);
        $rightNorm = $this->normalize($right);

        if ($leftNorm === '' || $rightNorm === '') {
            return 0.0;
        }

        if ($leftNorm === $rightNorm) {
            return 1.0;
        }

        similar_text($leftNorm, $rightNorm, $percent);

        return max(0.0, min(1.0, $percent / 100));
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text;
    }
}

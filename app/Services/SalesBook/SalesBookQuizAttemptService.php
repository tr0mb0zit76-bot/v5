<?php

namespace App\Services\SalesBook;

use App\Enums\SalesBookArticleStatus;
use App\Models\SalesBookArticle;
use App\Models\SalesBookQuizAttempt;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\SalesBookContentNormalizer;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class SalesBookQuizAttemptService
{
    public function __construct(
        private readonly SalesBookContentNormalizer $contentNormalizer,
    ) {}

    /**
     * @param  array<string, string>  $answers
     */
    public function record(User $user, SalesBookArticle $article, array $answers): SalesBookQuizAttempt
    {
        if (! Schema::hasTable('sales_book_quiz_attempts')) {
            throw new RuntimeException('Таблица результатов тестов ещё не создана.');
        }

        $this->ensureArticleAccessible($user, $article);

        $quiz = $this->contentNormalizer->parseQuiz((string) ($article->markdown_content ?? ''));

        if ($quiz === null || $quiz['questions'] === []) {
            throw ValidationException::withMessages([
                'answers' => 'На этой странице нет интерактивного теста.',
            ]);
        }

        $normalizedAnswers = $this->normalizeAnswers($answers);
        $score = 0;
        $totalQuestions = count($quiz['questions']);

        foreach ($quiz['questions'] as $question) {
            $questionId = (string) $question['id'];
            $selected = $normalizedAnswers[$questionId] ?? null;

            if ($selected === null) {
                throw ValidationException::withMessages([
                    'answers' => 'Ответьте на все вопросы теста.',
                ]);
            }

            $optionIds = array_column($question['options'], 'id');

            if (! in_array($selected, $optionIds, true)) {
                throw ValidationException::withMessages([
                    'answers' => 'Передан недопустимый вариант ответа.',
                ]);
            }

            if ($selected === $question['correct']) {
                $score++;
            }
        }

        return SalesBookQuizAttempt::query()->create([
            'sales_book_article_id' => $article->id,
            'user_id' => $user->id,
            'score' => $score,
            'total_questions' => $totalQuestions,
            'answers' => $normalizedAnswers,
            'completed_at' => now(),
        ]);
    }

    private function ensureArticleAccessible(User $user, SalesBookArticle $article): void
    {
        if (RoleAccess::canWriteSalesBook($user)) {
            return;
        }

        if ($article->status !== SalesBookArticleStatus::Published) {
            throw ValidationException::withMessages([
                'answers' => 'Страница недоступна для прохождения теста.',
            ]);
        }
    }

    /**
     * @param  array<string, string>  $answers
     * @return array<string, string>
     */
    private function normalizeAnswers(array $answers): array
    {
        $normalized = [];

        foreach ($answers as $questionId => $optionId) {
            $questionKey = trim((string) $questionId);
            $optionValue = trim((string) $optionId);

            if ($questionKey === '' || $optionValue === '') {
                continue;
            }

            $normalized[$questionKey] = mb_substr($optionValue, 0, 32);
        }

        return $normalized;
    }
}

<?php

namespace App\Services\SalesBook;

use App\Models\SalesBookArticle;
use App\Models\SalesBookQuizAttempt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class SalesBookQuizInsightsService
{
    /**
     * @return array{
     *     summary: array{
     *         attempts: int,
     *         unique_users: int,
     *         unique_articles: int,
     *         avg_score: float|null,
     *         avg_percent: float|null
     *     },
     *     by_article: list<array{
     *         article_id: int,
     *         title: string,
     *         attempts: int,
     *         unique_users: int,
     *         avg_score: float,
     *         avg_percent: float,
     *         last_attempt_at: string|null
     *     }>,
     *     by_user: list<array{
     *         user_id: int,
     *         name: string,
     *         attempts: int,
     *         avg_score: float,
     *         avg_percent: float,
     *         best_score: int,
     *         best_total: int,
     *         last_attempt_at: string|null
     *     }>,
     *     recent_attempts: list<array{
     *         id: int,
     *         user_id: int,
     *         user_name: string,
     *         article_id: int,
     *         article_title: string,
     *         score: int,
     *         total_questions: int,
     *         percent: int,
     *         completed_at: string|null
     *     }>
     * }
     */
    public function insights(int $days = 30, ?int $articleId = null, ?int $userId = null, int $limit = 20): array
    {
        if (! Schema::hasTable('sales_book_quiz_attempts')) {
            return $this->emptyInsights();
        }

        $days = max(1, min($days, 365));
        $limit = max(1, min($limit, 100));
        $since = now()->subDays($days);

        $baseQuery = SalesBookQuizAttempt::query()
            ->where('completed_at', '>=', $since);

        if ($articleId !== null) {
            $baseQuery->where('sales_book_article_id', $articleId);
        }

        if ($userId !== null) {
            $baseQuery->where('user_id', $userId);
        }

        $summaryRow = (clone $baseQuery)
            ->selectRaw('COUNT(*) as attempts_count')
            ->selectRaw('COUNT(DISTINCT user_id) as unique_users_count')
            ->selectRaw('COUNT(DISTINCT sales_book_article_id) as unique_articles_count')
            ->selectRaw('AVG(score) as avg_score')
            ->selectRaw('AVG(CASE WHEN total_questions > 0 THEN score / total_questions * 100 ELSE NULL END) as avg_percent')
            ->first();

        return [
            'summary' => [
                'window_days' => $days,
                'attempts' => (int) ($summaryRow->attempts_count ?? 0),
                'unique_users' => (int) ($summaryRow->unique_users_count ?? 0),
                'unique_articles' => (int) ($summaryRow->unique_articles_count ?? 0),
                'avg_score' => $summaryRow->avg_score !== null ? round((float) $summaryRow->avg_score, 1) : null,
                'avg_percent' => $summaryRow->avg_percent !== null ? round((float) $summaryRow->avg_percent, 1) : null,
            ],
            'by_article' => $this->byArticle($since, $articleId, $userId, $limit),
            'by_user' => $this->byUser($since, $articleId, $userId, $limit),
            'recent_attempts' => $this->recentAttempts($since, $articleId, $userId, $limit),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function participantUsers(int $days = 365, ?int $userId = null): array
    {
        if (! Schema::hasTable('sales_book_quiz_attempts')) {
            return [];
        }

        $since = now()->subDays(max(1, min($days, 365)));

        $userIdsQuery = SalesBookQuizAttempt::query()
            ->where('completed_at', '>=', $since);

        if ($userId !== null) {
            $userIdsQuery->where('user_id', $userId);
        }

        $userIds = $userIdsQuery
            ->distinct()
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    public function attemptedArticles(int $days = 365, ?int $userId = null): array
    {
        if (! Schema::hasTable('sales_book_quiz_attempts')) {
            return [];
        }

        $since = now()->subDays(max(1, min($days, 365)));

        $articleIdsQuery = SalesBookQuizAttempt::query()
            ->where('completed_at', '>=', $since);

        if ($userId !== null) {
            $articleIdsQuery->where('user_id', $userId);
        }

        $articleIds = $articleIdsQuery
            ->distinct()
            ->pluck('sales_book_article_id');

        if ($articleIds->isEmpty()) {
            return [];
        }

        return SalesBookArticle::query()
            ->whereIn('id', $articleIds)
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (SalesBookArticle $article): array => [
                'id' => $article->id,
                'title' => $article->title,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     article_id: int,
     *     title: string,
     *     attempts: int,
     *     unique_users: int,
     *     avg_score: float,
     *     avg_percent: float,
     *     last_attempt_at: string|null
     * }>
     */
    private function byArticle(mixed $since, ?int $articleId, ?int $userId, int $limit): array
    {
        $attempt = new SalesBookQuizAttempt;
        $article = new SalesBookArticle;
        $attemptTable = $attempt->getTable();
        $articleTable = $article->getTable();

        $query = SalesBookQuizAttempt::query()
            ->join($articleTable, "{$articleTable}.id", '=', "{$attemptTable}.sales_book_article_id")
            ->where("{$attemptTable}.completed_at", '>=', $since)
            ->select("{$articleTable}.id", "{$articleTable}.title")
            ->selectRaw("COUNT({$attemptTable}.id) as attempts_count")
            ->selectRaw("COUNT(DISTINCT {$attemptTable}.user_id) as unique_users_count")
            ->selectRaw("AVG({$attemptTable}.score) as avg_score")
            ->selectRaw("AVG(CASE WHEN {$attemptTable}.total_questions > 0 THEN {$attemptTable}.score / {$attemptTable}.total_questions * 100 ELSE NULL END) as avg_percent")
            ->selectRaw("MAX({$attemptTable}.completed_at) as last_attempt_at")
            ->groupBy("{$articleTable}.id", "{$articleTable}.title")
            ->orderByDesc('attempts_count')
            ->limit($limit);

        if ($articleId !== null) {
            $query->where("{$attemptTable}.sales_book_article_id", $articleId);
        }

        if ($userId !== null) {
            $query->where("{$attemptTable}.user_id", $userId);
        }

        /** @var Collection<int, object> $rows */
        $rows = $query->get();

        return $rows->map(fn (object $row): array => [
            'article_id' => (int) $row->id,
            'title' => (string) $row->title,
            'attempts' => (int) ($row->attempts_count ?? 0),
            'unique_users' => (int) ($row->unique_users_count ?? 0),
            'avg_score' => round((float) ($row->avg_score ?? 0), 1),
            'avg_percent' => round((float) ($row->avg_percent ?? 0), 1),
            'last_attempt_at' => $row->last_attempt_at !== null ? (string) $row->last_attempt_at : null,
        ])->values()->all();
    }

    /**
     * @return list<array{
     *     user_id: int,
     *     name: string,
     *     attempts: int,
     *     avg_score: float,
     *     avg_percent: float,
     *     best_score: int,
     *     best_total: int,
     *     last_attempt_at: string|null
     * }>
     */
    private function byUser(mixed $since, ?int $articleId, ?int $userId, int $limit): array
    {
        $attempt = new SalesBookQuizAttempt;
        $user = new User;
        $attemptTable = $attempt->getTable();
        $userTable = $user->getTable();

        $query = SalesBookQuizAttempt::query()
            ->join($userTable, "{$userTable}.id", '=', "{$attemptTable}.user_id")
            ->where("{$attemptTable}.completed_at", '>=', $since)
            ->select("{$userTable}.id", "{$userTable}.name")
            ->selectRaw("COUNT({$attemptTable}.id) as attempts_count")
            ->selectRaw("AVG({$attemptTable}.score) as avg_score")
            ->selectRaw("AVG(CASE WHEN {$attemptTable}.total_questions > 0 THEN {$attemptTable}.score / {$attemptTable}.total_questions * 100 ELSE NULL END) as avg_percent")
            ->selectRaw("MAX({$attemptTable}.score) as best_score")
            ->selectRaw("MAX({$attemptTable}.total_questions) as max_total_questions")
            ->selectRaw("MAX({$attemptTable}.completed_at) as last_attempt_at")
            ->groupBy("{$userTable}.id", "{$userTable}.name")
            ->orderByDesc('attempts_count')
            ->limit($limit);

        if ($articleId !== null) {
            $query->where("{$attemptTable}.sales_book_article_id", $articleId);
        }

        if ($userId !== null) {
            $query->where("{$attemptTable}.user_id", $userId);
        }

        /** @var Collection<int, object> $rows */
        $rows = $query->get();

        return $rows->map(function (object $row): array {
            $bestScore = (int) ($row->best_score ?? 0);
            $bestTotal = (int) ($row->max_total_questions ?? 0);

            if ($bestTotal <= 0) {
                $bestTotal = max($bestScore, 1);
            }

            return [
                'user_id' => (int) $row->id,
                'name' => (string) $row->name,
                'attempts' => (int) ($row->attempts_count ?? 0),
                'avg_score' => round((float) ($row->avg_score ?? 0), 1),
                'avg_percent' => round((float) ($row->avg_percent ?? 0), 1),
                'best_score' => $bestScore,
                'best_total' => $bestTotal,
                'last_attempt_at' => $row->last_attempt_at !== null ? (string) $row->last_attempt_at : null,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{
     *     id: int,
     *     user_id: int,
     *     user_name: string,
     *     article_id: int,
     *     article_title: string,
     *     score: int,
     *     total_questions: int,
     *     percent: int,
     *     completed_at: string|null
     * }>
     */
    private function recentAttempts(mixed $since, ?int $articleId, ?int $userId, int $limit): array
    {
        $query = SalesBookQuizAttempt::query()
            ->with(['user:id,name', 'article:id,title'])
            ->where('completed_at', '>=', $since)
            ->orderByDesc('completed_at')
            ->limit($limit);

        if ($articleId !== null) {
            $query->where('sales_book_article_id', $articleId);
        }

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(function (SalesBookQuizAttempt $attempt): array {
            $total = max(1, (int) $attempt->total_questions);

            return [
                'id' => $attempt->id,
                'user_id' => $attempt->user_id,
                'user_name' => (string) ($attempt->user?->name ?? '—'),
                'article_id' => $attempt->sales_book_article_id,
                'article_title' => (string) ($attempt->article?->title ?? '—'),
                'score' => (int) $attempt->score,
                'total_questions' => (int) $attempt->total_questions,
                'percent' => (int) round(((int) $attempt->score / $total) * 100),
                'completed_at' => $attempt->completed_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @return array{
     *     summary: array{
     *         attempts: int,
     *         unique_users: int,
     *         unique_articles: int,
     *         avg_score: float|null,
     *         avg_percent: float|null
     *     },
     *     by_article: list<array<string, mixed>>,
     *     by_user: list<array<string, mixed>>,
     *     recent_attempts: list<array<string, mixed>>
     * }
     */
    private function emptyInsights(): array
    {
        return [
            'summary' => [
                'window_days' => 0,
                'attempts' => 0,
                'unique_users' => 0,
                'unique_articles' => 0,
                'avg_score' => null,
                'avg_percent' => null,
            ],
            'by_article' => [],
            'by_user' => [],
            'recent_attempts' => [],
        ];
    }
}

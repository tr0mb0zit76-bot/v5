<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SalesBookArticle;
use App\Models\SalesBookQuizAttempt;
use App\Models\User;
use Tests\TestCase;

class SalesBookQuizAttemptTest extends TestCase
{
    public function test_reader_can_submit_quiz_attempt_and_score_is_calculated_on_server(): void
    {
        $user = $this->makeReader();

        $article = SalesBookArticle::query()->create([
            'title' => 'Тестовая страница',
            'markdown_content' => $this->quizMarkdown(),
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('sales-assistant.book.articles.quiz-attempt', $article), [
                'answers' => [
                    'q1' => 'b',
                    'q2' => 'a',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('attempt.score', 1)
            ->assertJsonPath('attempt.total_questions', 2);

        $this->assertDatabaseHas('sales_book_quiz_attempts', [
            'sales_book_article_id' => $article->id,
            'user_id' => $user->id,
            'score' => 1,
            'total_questions' => 2,
        ]);
    }

    public function test_quiz_attempt_is_forbidden_without_book_visibility(): void
    {
        $role = Role::query()->create([
            'name' => 'no_book_'.uniqid(),
            'display_name' => 'No book',
            'permissions' => ['sales_book_read'],
            'visibility_areas' => ['sales_assistant_scripts'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $article = SalesBookArticle::query()->create([
            'title' => 'Тест',
            'markdown_content' => $this->quizMarkdown(),
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('sales-assistant.book.articles.quiz-attempt', $article), [
                'answers' => ['q1' => 'b', 'q2' => 'a'],
            ])
            ->assertForbidden();
    }

    public function test_quiz_attempt_requires_all_answers(): void
    {
        $user = $this->makeReader();

        $article = SalesBookArticle::query()->create([
            'title' => 'Тест',
            'markdown_content' => $this->quizMarkdown(),
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('sales-assistant.book.articles.quiz-attempt', $article), [
                'answers' => ['q1' => 'b'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_supervisor_sees_team_quiz_analytics_on_dedicated_page(): void
    {
        $supervisorRole = Role::query()->create([
            'name' => 'supervisor',
            'display_name' => 'Supervisor',
            'permissions' => ['sales_book_read'],
            'visibility_areas' => ['sales_assistant_book'],
        ]);

        $supervisor = User::factory()->create(['role_id' => $supervisorRole->id]);
        $employee = User::factory()->create(['role_id' => $this->makeReaderRole()->id]);

        $article = SalesBookArticle::query()->create([
            'title' => 'Тест',
            'markdown_content' => $this->quizMarkdown(),
            'sort_order' => 0,
        ]);

        SalesBookQuizAttempt::query()->create([
            'sales_book_article_id' => $article->id,
            'user_id' => $employee->id,
            'score' => 2,
            'total_questions' => 2,
            'answers' => ['q1' => 'b', 'q2' => 'b'],
            'completed_at' => now(),
        ]);

        $this->actingAs($supervisor)
            ->get(route('sales-assistant.book.quiz-analytics'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SalesAssistant/BookQuizAnalytics')
                ->where('filters.can_view_all', true)
                ->where('insights.summary.attempts', 1)
                ->where('insights.summary.unique_users', 1)
            );
    }

    public function test_reader_sees_only_own_quiz_analytics(): void
    {
        $reader = $this->makeReader();
        $otherUser = User::factory()->create(['role_id' => $this->makeReaderRole()->id]);

        $article = SalesBookArticle::query()->create([
            'title' => 'Тест',
            'markdown_content' => $this->quizMarkdown(),
            'sort_order' => 0,
        ]);

        SalesBookQuizAttempt::query()->create([
            'sales_book_article_id' => $article->id,
            'user_id' => $otherUser->id,
            'score' => 2,
            'total_questions' => 2,
            'answers' => ['q1' => 'b', 'q2' => 'b'],
            'completed_at' => now(),
        ]);

        $this->actingAs($reader)
            ->get(route('sales-assistant.book.quiz-analytics'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.can_view_all', false)
                ->where('filters.user_id', $reader->id)
                ->where('insights.summary.attempts', 0)
            );
    }

    private function makeReader(): User
    {
        return User::factory()->create([
            'role_id' => $this->makeReaderRole()->id,
        ]);
    }

    private function makeReaderRole(): Role
    {
        return Role::query()->create([
            'name' => 'reader_'.uniqid(),
            'display_name' => 'Reader',
            'permissions' => ['sales_book_read'],
            'visibility_areas' => ['sales_assistant_book'],
        ]);
    }

    private function quizMarkdown(): string
    {
        return <<<'MD'
# Тест

<!-- sales-book:quiz -->
{"questions":[{"id":"q1","text":"Q1?","options":[{"id":"a","text":"A"},{"id":"b","text":"B"}],"correct":"b"},{"id":"q2","text":"Q2?","options":[{"id":"a","text":"A"},{"id":"b","text":"B"}],"correct":"b"}]}
<!-- /sales-book:quiz -->
MD;
    }
}

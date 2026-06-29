<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SalesBookArticle;
use App\Models\SalesBookQuizAttempt;
use App\Models\User;
use Tests\TestCase;

class SalesBookQuizAnalyticsPageTest extends TestCase
{
    public function test_quiz_analytics_page_is_forbidden_without_book_visibility(): void
    {
        $role = Role::query()->create([
            'name' => 'no_book_'.uniqid(),
            'display_name' => 'No book',
            'permissions' => ['sales_book_read'],
            'visibility_areas' => ['sales_assistant_scripts'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get(route('sales-assistant.book.quiz-analytics'))
            ->assertForbidden();
    }

    public function test_quiz_analytics_page_is_available_with_analytics_area(): void
    {
        $manager = $this->makeManagerWithQuizAnalytics();

        $article = SalesBookArticle::query()->create([
            'title' => 'Тест «Возражения»',
            'markdown_content' => '# Тест',
            'sort_order' => 0,
        ]);

        $employee = User::factory()->create(['name' => 'Сотрудник']);

        SalesBookQuizAttempt::query()->create([
            'sales_book_article_id' => $article->id,
            'user_id' => $employee->id,
            'score' => 9,
            'total_questions' => 10,
            'answers' => ['q1' => 'a'],
            'completed_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('sales-assistant.book.quiz-analytics'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SalesAssistant/BookQuizAnalytics')
                ->where('insights.summary.attempts', 1)
                ->where('insights.summary.unique_users', 1)
            );
    }

    public function test_quiz_analytics_page_filters_by_user(): void
    {
        $manager = $this->makeManagerWithQuizAnalytics();

        $article = SalesBookArticle::query()->create([
            'title' => 'Тест',
            'markdown_content' => '# Тест',
            'sort_order' => 0,
        ]);

        $firstUser = User::factory()->create(['name' => 'Первый']);
        $secondUser = User::factory()->create(['name' => 'Второй']);

        SalesBookQuizAttempt::query()->create([
            'sales_book_article_id' => $article->id,
            'user_id' => $firstUser->id,
            'score' => 8,
            'total_questions' => 10,
            'answers' => [],
            'completed_at' => now(),
        ]);

        SalesBookQuizAttempt::query()->create([
            'sales_book_article_id' => $article->id,
            'user_id' => $secondUser->id,
            'score' => 10,
            'total_questions' => 10,
            'answers' => [],
            'completed_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('sales-assistant.book.quiz-analytics', ['user_id' => $firstUser->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.user_id', $firstUser->id)
                ->where('insights.summary.attempts', 1)
            );
    }

    private function makeManagerWithQuizAnalytics(): User
    {
        $role = Role::query()->create([
            'name' => 'supervisor',
            'display_name' => 'Supervisor',
            'permissions' => ['sales_book_read'],
            'visibility_areas' => ['sales_assistant_book'],
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SalesBookArticle;
use App\Models\User;
use Tests\TestCase;

class SalesBookAccessTest extends TestCase
{
    public function test_book_page_is_available_with_book_visibility_and_default_read_access(): void
    {
        $role = Role::query()->create([
            'name' => 'restricted_manager',
            'display_name' => 'Restricted manager',
            'permissions' => [],
            'visibility_areas' => ['scripts', 'sales_assistant_book'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user)
            ->get(route('sales-assistant.book'))
            ->assertOk();
    }

    public function test_book_page_is_available_with_read_permission(): void
    {
        $role = Role::query()->create([
            'name' => 'reader_manager',
            'display_name' => 'Reader manager',
            'permissions' => ['sales_book_read'],
            'visibility_areas' => ['scripts', 'sales_assistant_book'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user)
            ->get(route('sales-assistant.book'))
            ->assertOk();
    }

    public function test_book_page_exposes_read_only_capabilities_for_read_permission(): void
    {
        $role = Role::query()->create([
            'name' => 'reader_manager',
            'display_name' => 'Reader manager',
            'permissions' => ['sales_book_read'],
            'visibility_areas' => ['scripts', 'sales_assistant_book'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user)
            ->get(route('sales-assistant.book'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('capabilities.can_read', true)
                ->where('capabilities.can_comment', false)
                ->where('capabilities.can_write', false)
            );
    }

    public function test_read_only_user_cannot_update_book_article(): void
    {
        $role = Role::query()->create([
            'name' => 'reader_manager',
            'display_name' => 'Reader manager',
            'permissions' => ['sales_book_read'],
            'visibility_areas' => ['scripts', 'sales_assistant_book'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $article = SalesBookArticle::query()->create([
            'title' => 'Тестовая страница',
            'markdown_content' => 'Исходный текст',
            'sort_order' => 0,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->patch(route('sales-assistant.book.articles.update', $article), [
                'title' => 'Новый заголовок',
                'markdown_content' => 'Новый текст',
            ])
            ->assertForbidden();
    }

    public function test_reader_sees_updated_markdown_after_writer_saves(): void
    {
        $writerRole = Role::query()->create([
            'name' => 'writer_manager',
            'display_name' => 'Writer manager',
            'permissions' => ['sales_book_write'],
            'visibility_areas' => ['scripts', 'sales_assistant_book'],
        ]);

        $readerRole = Role::query()->create([
            'name' => 'reader_manager',
            'display_name' => 'Reader manager',
            'permissions' => ['sales_book_read'],
            'visibility_areas' => ['scripts', 'sales_assistant_book'],
        ]);

        $writer = User::factory()->create(['role_id' => $writerRole->id]);
        $reader = User::factory()->create(['role_id' => $readerRole->id]);

        $article = SalesBookArticle::query()->create([
            'title' => 'Страница',
            'markdown_content' => "```\nстарый код\n```",
            'sort_order' => 0,
            'created_by' => $writer->id,
        ]);

        $this->actingAs($writer)
            ->patch(route('sales-assistant.book.articles.update', $article), [
                'title' => 'Страница',
                'markdown_content' => 'Обычный текст без блока кода',
            ])
            ->assertRedirect(route('sales-assistant.book', ['article_id' => $article->id]));

        $this->actingAs($reader)
            ->get(route('sales-assistant.book', ['article_id' => $article->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('selectedArticle.markdown_content', 'Обычный текст без блока кода')
            );
    }

    public function test_writer_can_save_sales_book_article_properties(): void
    {
        $role = Role::query()->create([
            'name' => 'writer_manager',
            'display_name' => 'Writer manager',
            'permissions' => ['sales_book_write'],
            'visibility_areas' => ['scripts', 'sales_assistant_book'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $article = SalesBookArticle::query()->create([
            'title' => 'Материал по КП',
            'markdown_content' => 'Исходный текст',
            'sort_order' => 0,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->patch(route('sales-assistant.book.articles.update', $article), [
                'title' => 'Материал по КП',
                'markdown_content' => 'Обновленный текст',
                'status' => 'published',
                'tags' => ['КП'],
                'content_format' => 'markdown',
                'properties' => [
                    'audience_role' => 'manager',
                    'sales_stage' => 'offer',
                    'unknown' => 'drop-me',
                ],
            ])
            ->assertRedirect(route('sales-assistant.book', ['article_id' => $article->id]));

        $article->refresh();

        $this->assertSame('markdown', $article->content_format);
        $this->assertEquals([
            'audience_role' => 'manager',
            'sales_stage' => 'offer',
        ], $article->properties);
        $this->assertSame('sales_book_blocks_v1', $article->blocks_snapshot['schema']);
        $this->assertSame('paragraph', $article->blocks_snapshot['blocks'][0]['type']);
        $this->assertSame('Обновленный текст', $article->blocks_snapshot['blocks'][0]['text']);
    }

    public function test_book_page_resolves_embedded_article_collections(): void
    {
        $role = Role::query()->create([
            'name' => 'reader_manager',
            'display_name' => 'Reader manager',
            'permissions' => ['sales_book_read'],
            'visibility_areas' => ['scripts', 'sales_assistant_book'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $navigator = SalesBookArticle::query()->create([
            'title' => 'Навигатор КП',
            'markdown_content' => <<<'MD'
# Навигатор КП

```sales-book-view
{
  "title": "КП для менеджера",
  "view_slug": "manager-materials",
  "filters": {"sales_stage": "offer"},
  "limit": 5
}
```
MD,
            'sort_order' => 0,
        ]);

        $matching = SalesBookArticle::query()->create([
            'title' => 'Как отправить КП',
            'markdown_content' => 'Инструкция.',
            'sort_order' => 1,
            'properties' => [
                'audience_role' => 'manager',
                'sales_stage' => 'offer',
            ],
        ]);

        SalesBookArticle::query()->create([
            'title' => 'Материал для логиста',
            'markdown_content' => 'Не должен попасть.',
            'sort_order' => 2,
            'properties' => [
                'audience_role' => 'logist',
                'sales_stage' => 'offer',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('sales-assistant.book', ['article_id' => $navigator->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('selectedArticle.embedded_collections.0.title', 'КП для менеджера')
                ->where('selectedArticle.embedded_collections.0.rows.0.id', $matching->id)
                ->where('selectedArticle.embedded_collections.0.rows.0.title', 'Как отправить КП')
                ->where('selectedArticle.markdown_content_display', '# Навигатор КП')
            );
    }

    public function test_book_page_uses_backend_search_for_sidebar_results(): void
    {
        $role = Role::query()->create([
            'name' => 'reader_manager',
            'display_name' => 'Reader manager',
            'permissions' => ['sales_book_read'],
            'visibility_areas' => ['scripts', 'sales_assistant_book'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $matching = SalesBookArticle::query()->create([
            'title' => 'Материал без ключевого слова в заголовке',
            'markdown_content' => 'Внутри статьи есть редкое слово альфацентавра для backend-поиска.',
            'sort_order' => 1,
            'properties' => [
                'audience_role' => 'manager',
            ],
        ]);

        SalesBookArticle::query()->create([
            'title' => 'Другой материал',
            'markdown_content' => 'Обычная статья без совпадения.',
            'sort_order' => 2,
            'properties' => [
                'audience_role' => 'logist',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('sales-assistant.book', [
                'q' => 'альфацентавра',
                'filters' => [
                    'audience_role' => 'manager',
                ],
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('bookSearch.query', 'альфацентавра')
                ->where('bookSearch.filters.audience_role', 'manager')
                ->where('bookViewRows.0.id', $matching->id)
                ->where('bookViewRows.0.matched_in', 'content')
                ->where('selectedArticle.id', $matching->id)
            );
    }

    public function test_book_page_is_forbidden_without_book_visibility_area(): void
    {
        $role = Role::query()->create([
            'name' => 'scripts_only_manager',
            'display_name' => 'Scripts only manager',
            'permissions' => ['sales_book_write'],
            'visibility_areas' => ['scripts', 'sales_assistant_scripts'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($user)
            ->get(route('sales-assistant.book'))
            ->assertForbidden();
    }
}

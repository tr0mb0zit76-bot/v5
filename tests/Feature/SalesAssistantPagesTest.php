<?php

namespace Tests\Feature;

use App\Models\SalesBookArticle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesAssistantPagesTest extends TestCase
{
    public function test_guest_is_redirected_from_sales_assistant_book(): void
    {
        $this->get(route('sales-assistant.book'))->assertRedirect();
    }

    public function test_user_without_scripts_area_cannot_access_sales_assistant_pages(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'no_sales_assistant',
            'display_name' => 'No sales assistant',
            'visibility_areas' => json_encode(['dashboard'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('sales-assistant.book'))->assertForbidden();
        $this->actingAs($user)->get(route('sales-assistant.trainer'))->assertForbidden();
    }

    public function test_user_with_scripts_area_can_open_sales_assistant_stubs(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'with_scripts_stub',
            'display_name' => 'With scripts',
            'visibility_areas' => json_encode(['dashboard', 'scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('sales-assistant.book'))->assertOk();
        $this->actingAs($user)->get(route('sales-assistant.trainer'))->assertOk();
    }

    public function test_scripts_user_can_create_update_and_delete_sales_book_article(): void
    {
        $user = $this->createUserWithAreas(['dashboard', 'scripts']);

        $this->actingAs($user)->post(route('sales-assistant.book.articles.store'), [
            'title' => '������ ������',
            'markdown_content' => "# ���������\n\n�����",
            'parent_id' => null,
        ])->assertRedirect();

        $article = SalesBookArticle::query()->where('title', '������ ������')->first();
        $this->assertNotNull($article);

        $this->actingAs($user)->patch(route('sales-assistant.book.articles.update', $article), [
            'title' => '������ ������ (���������)',
            'markdown_content' => "## ���������\n\n����� �����",
            'parent_id' => null,
        ])->assertRedirect();

        $article->refresh();
        $this->assertSame('������ ������ (���������)', $article->title);

        $this->actingAs($user)->delete(route('sales-assistant.book.articles.destroy', $article))
            ->assertRedirect(route('sales-assistant.book'));

        $this->assertDatabaseMissing('sales_book_articles', [
            'id' => $article->id,
        ]);
    }

    public function test_scripts_user_can_import_markdown_file(): void
    {
        $user = $this->createUserWithAreas(['dashboard', 'scripts']);

        $file = UploadedFile::fake()->createWithContent('upsell-guide.md', "# Гайд по допродажам\n\n- Шаг 1\n- Шаг 2");

        $this->actingAs($user)->post(route('sales-assistant.book.import'), [
            'file' => $file,
        ])->assertRedirect();

        $article = SalesBookArticle::query()->first();
        $this->assertNotNull($article);
        $this->assertSame('Гайд по допродажам', $article->title);
        $this->assertStringContainsString('Шаг 1', $article->markdown_content);

        $this->actingAs($user)
            ->get(route('sales-assistant.book', ['article_id' => $article->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SalesAssistant/Book')
                ->where('selectedArticle.id', $article->id)
                ->where('selectedArticle.title', 'Гайд по допродажам')
                ->where('selectedArticle.markdown_content', fn (string $markdown): bool => str_contains($markdown, '# Гайд по допродажам')
                    && str_contains($markdown, 'Шаг 1'))
            );
    }

    public function test_update_article_preserves_nested_markdown_lists(): void
    {
        $user = $this->createUserWithAreas(['dashboard', 'scripts']);

        $markdown = <<<'MD'
# Заголовок

1. Первый пункт
   1. Вложенный пункт
   2. Ещё вложенный
2. Второй пункт

> Цитата с **жирным** текстом
MD;

        $this->actingAs($user)->post(route('sales-assistant.book.articles.store'), [
            'title' => 'Nested list article',
            'markdown_content' => $markdown,
            'parent_id' => null,
        ])->assertRedirect();

        $article = SalesBookArticle::query()->where('title', 'Nested list article')->first();
        $this->assertNotNull($article);
        $this->assertStringContainsString('Вложенный пункт', $article->markdown_content);
        $this->assertStringContainsString('**жирным**', $article->markdown_content);

        $this->actingAs($user)
            ->get(route('sales-assistant.book', ['article_id' => $article->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SalesAssistant/Book')
                ->where('selectedArticle.markdown_content', $markdown)
            );
    }

    public function test_import_rejects_non_markdown_file(): void
    {
        $user = $this->createUserWithAreas(['dashboard', 'scripts']);

        $file = UploadedFile::fake()->create('binary.pdf', 10, 'application/pdf');

        $this->actingAs($user)
            ->from(route('sales-assistant.book'))
            ->post(route('sales-assistant.book.import'), [
                'file' => $file,
            ])
            ->assertRedirect(route('sales-assistant.book'))
            ->assertSessionHasErrors('file');
    }

    public function test_import_preserves_markdown_table(): void
    {
        $user = $this->createUserWithAreas(['dashboard', 'scripts']);

        $markdown = <<<'MD'
# Таблица тарифов

| Колонка A | Колонка B |
| --- | --- |
| 100 | 200 |
| 300 | 400 |
MD;

        $file = UploadedFile::fake()->createWithContent('tariffs.md', $markdown);

        $this->actingAs($user)->post(route('sales-assistant.book.import'), [
            'file' => $file,
        ])->assertRedirect();

        $article = SalesBookArticle::query()->where('title', 'Таблица тарифов')->first();
        $this->assertNotNull($article);
        $this->assertStringContainsString('| Колонка A | Колонка B |', $article->markdown_content);
        $this->assertStringContainsString('| 100 | 200 |', $article->markdown_content);

        $this->actingAs($user)
            ->get(route('sales-assistant.book', ['article_id' => $article->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SalesAssistant/Book')
                ->where('selectedArticle.markdown_content', fn (string $content): bool => str_contains($content, '| Колонка A | Колонка B |')
                    && str_contains($content, '| 100 | 200 |'))
            );
    }

    public function test_import_normalizes_indented_markdown_table(): void
    {
        $user = $this->createUserWithAreas(['dashboard', 'scripts']);

        $markdown = <<<'MD'
# Импорт с отступами

    | Ситуация | Что делать |
    | --- | --- |
    | Ошибка | Исправить |
MD;

        $file = UploadedFile::fake()->createWithContent('indented-table.md', $markdown);

        $this->actingAs($user)->post(route('sales-assistant.book.import'), [
            'file' => $file,
        ])->assertRedirect();

        $article = SalesBookArticle::query()->where('title', 'Импорт с отступами')->first();
        $this->assertNotNull($article);
        $this->assertStringContainsString("| Ситуация | Что делать |\n| --- | --- |", $article->markdown_content);
        $this->assertStringNotContainsString('    | Ситуация', $article->markdown_content);
    }

    public function test_import_order_wizard_guide_serves_body_without_local_image_urls(): void
    {
        $user = $this->createUserWithAreas(['dashboard', 'scripts']);

        $sourcePath = base_path('docs/order-wizard-user-guide.md');
        $this->assertFileExists($sourcePath);

        $file = new UploadedFile(
            $sourcePath,
            'order-wizard-user-guide.md',
            'text/markdown',
            null,
            true,
        );

        $this->actingAs($user)->post(route('sales-assistant.book.import'), [
            'file' => $file,
        ])->assertRedirect();

        $article = SalesBookArticle::query()
            ->where('title', 'Мастер заказов — инструкция для пользователя')
            ->first();

        $this->assertNotNull($article);
        $this->assertGreaterThan(5000, strlen($article->markdown_content ?? ''));
        $this->assertStringContainsString('## 1. Как открыть мастер', $article->markdown_content);
        $this->assertStringNotContainsString('marktext', $article->markdown_content);
        $this->assertStringNotContainsString('![](C:', $article->markdown_content);

        $this->actingAs($user)
            ->get(route('sales-assistant.book', ['article_id' => $article->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SalesAssistant/Book')
                ->where('selectedArticle.id', $article->id)
                ->where('selectedArticle.markdown_content', fn (string $content): bool => str_contains($content, '## 2. Минимальный')
                    && str_contains($content, 'загрузите изображение через кнопку «Картинка»')
                    && ! str_contains($content, 'marktext'))
            );
    }

    public function test_import_vehicle_user_guide_preserves_body_for_editor(): void
    {
        $user = $this->createUserWithAreas(['dashboard', 'scripts']);

        $parent = SalesBookArticle::query()->create([
            'title' => 'Руководство по CRM',
            'markdown_content' => '# Руководство по CRM',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $sourcePath = base_path('docs/vehicle-user-guide.md');
        $this->assertFileExists($sourcePath);

        $file = new UploadedFile(
            $sourcePath,
            'vehicle-user-guide.md',
            'text/markdown',
            null,
            true,
        );

        $this->actingAs($user)->post(route('sales-assistant.book.import'), [
            'file' => $file,
            'parent_id' => $parent->id,
        ])->assertRedirect();

        $article = SalesBookArticle::query()
            ->where('title', 'Авто (транспортные средства) — инструкция для пользователя')
            ->first();

        $this->assertNotNull($article);
        $this->assertSame($parent->id, $article->parent_id);
        $this->assertGreaterThan(3000, strlen($article->markdown_content ?? ''));
        $this->assertStringContainsString('## 1. Как открыть раздел', $article->markdown_content);

        $this->actingAs($user)
            ->get(route('sales-assistant.book', ['article_id' => $article->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SalesAssistant/Book')
                ->where('selectedArticle.markdown_content', fn (string $content): bool => str_contains($content, '## 1. Как открыть раздел'))
            );
    }

    public function test_uploaded_sales_book_asset_is_private_and_requires_access(): void
    {
        Storage::fake('local');

        $writer = $this->createUserWithAreas(['dashboard', 'scripts']);
        $reader = $this->createUserWithAreas(['dashboard', 'scripts']);
        $noAccessUser = $this->createUserWithAreas(['dashboard']);

        $response = $this->actingAs($writer)->post(route('sales-assistant.book.assets.upload'), [
            'file' => UploadedFile::fake()->image('sales-book-image.png'),
        ]);

        $response->assertOk();

        Storage::disk('local')->assertCount('sales-book-assets', 1);

        $assetUrl = $response->json('url');
        $this->assertIsString($assetUrl);
        $this->assertStringContainsString('/sales-assistant/book/assets?', $assetUrl);
        $this->assertStringContainsString('path=sales-book-assets%2F', $assetUrl);

        $this->actingAs($reader)->get($assetUrl)->assertOk();
        $this->actingAs($noAccessUser)->get($assetUrl)->assertForbidden();

        auth()->logout();
        $this->get($assetUrl)->assertRedirect(route('login'));
    }

    public function test_user_with_sales_book_read_permission_can_open_book_but_cannot_edit(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'sales_book_read_only',
            'display_name' => 'Sales Book Read Only',
            'permissions' => json_encode(['sales_book_read'], JSON_THROW_ON_ERROR),
            'visibility_areas' => json_encode(['dashboard', 'scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get(route('sales-assistant.book'))->assertOk();

        $this->actingAs($user)->post(route('sales-assistant.book.articles.store'), [
            'title' => '����������� ������',
            'markdown_content' => 'test',
        ])->assertForbidden();
    }

    public function test_user_with_sales_book_write_permission_can_create_article(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'sales_book_write_allowed',
            'display_name' => 'Sales Book Write',
            'permissions' => json_encode(['sales_book_write'], JSON_THROW_ON_ERROR),
            'visibility_areas' => json_encode(['dashboard', 'scripts'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post(route('sales-assistant.book.articles.store'), [
            'title' => '��������� ������',
            'markdown_content' => 'test',
        ])->assertRedirect();

        $this->assertDatabaseHas('sales_book_articles', [
            'title' => '��������� ������',
        ]);
    }

    public function test_update_article_parent_moves_in_tree(): void
    {
        $user = $this->createUserWithAreas(['dashboard', 'scripts']);

        $parent = SalesBookArticle::query()->create([
            'title' => 'Parent page',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $child = SalesBookArticle::query()->create([
            'title' => 'Child page',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->patch(route('sales-assistant.book.articles.update', $child), [
            'title' => 'Child page',
            'markdown_content' => '',
            'parent_id' => $parent->id,
        ])->assertRedirect();

        $child->refresh();
        $this->assertSame($parent->id, $child->parent_id);

        $this->actingAs($user)
            ->get(route('sales-assistant.book', ['article_id' => $child->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SalesAssistant/Book')
                ->where('articlesTree', function ($tree) use ($parent, $child): bool {
                    $tree = is_array($tree) ? $tree : $tree->all();
                    if (count($tree) !== 1 || $tree[0]['id'] !== $parent->id) {
                        return false;
                    }

                    return count($tree[0]['children']) === 1
                        && $tree[0]['children'][0]['id'] === $child->id;
                })
            );
    }

    public function test_creating_child_updates_parent_markdown_with_link(): void
    {
        $user = $this->createUserWithAreas(['dashboard', 'scripts']);

        $parent = SalesBookArticle::query()->create([
            'title' => 'Parent page',
            'markdown_content' => 'Краткое введение.',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $this->actingAs($user)->post(route('sales-assistant.book.articles.store'), [
            'title' => 'Child page',
            'markdown_content' => '',
            'parent_id' => $parent->id,
        ])->assertRedirect();

        $child = SalesBookArticle::query()->where('title', 'Child page')->first();
        $this->assertNotNull($child);

        $parent->refresh();

        $this->assertStringContainsString(
            sprintf('- [Child page](/sales-assistant/book?article_id=%d)', $child->id),
            $parent->markdown_content,
        );
    }

    public function test_update_parent_without_markdown_preserves_content(): void
    {
        $user = $this->createUserWithAreas(['dashboard', 'scripts']);

        $parent = SalesBookArticle::query()->create([
            'title' => 'Parent page',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $child = SalesBookArticle::query()->create([
            'title' => 'Child page',
            'markdown_content' => "# Заголовок\n\n**жирный** текст",
            'parent_id' => null,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->patch(route('sales-assistant.book.articles.update', $child), [
            'title' => 'Child page',
            'parent_id' => $parent->id,
        ])->assertRedirect();

        $child->refresh();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame("# Заголовок\n\n**жирный** текст", $child->markdown_content);
    }

    public function test_move_article_endpoint_updates_hierarchy(): void
    {
        $user = $this->createUserWithAreas(['dashboard', 'scripts']);

        $parent = SalesBookArticle::query()->create([
            'title' => 'Parent page',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $child = SalesBookArticle::query()->create([
            'title' => 'Child page',
            'markdown_content' => '',
            'parent_id' => null,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->patch(route('sales-assistant.book.articles.move', $child), [
            'parent_id' => $parent->id,
            'sort_order' => 0,
        ])->assertRedirect();

        $child->refresh();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame(0, $child->sort_order);
    }

    /**
     * @param  list<string>  $areas
     * @param  list<string>  $permissions
     */
    private function createUserWithAreas(array $areas, array $permissions = ['sales_book_write']): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'sales_book_role_'.uniqid(),
            'display_name' => 'Sales book role',
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'visibility_areas' => json_encode($areas, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);
    }
}

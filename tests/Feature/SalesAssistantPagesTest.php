<?php

namespace Tests\Feature;

use App\Models\SalesBookArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesAssistantPagesTest extends TestCase
{
    use RefreshDatabase;

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

        $file = UploadedFile::fake()->createWithContent('upsell-guide.md', "# ������ �������\n\n- ��� 1\n- ��� 2");

        $this->actingAs($user)->post(route('sales-assistant.book.import'), [
            'file' => $file,
        ])->assertRedirect();

        $article = SalesBookArticle::query()->first();
        $this->assertNotNull($article);
        $this->assertSame('������ �������', $article->title);
        $this->assertStringContainsString('��� 1', $article->markdown_content);

        $this->actingAs($user)
            ->get(route('sales-assistant.book', ['article_id' => $article->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SalesAssistant/Book')
                ->where('selectedArticle.id', $article->id)
                ->where('selectedArticle.title', '������ �������')
                ->where('selectedArticle.html_content', fn (string $html): bool => str_contains($html, '<h1>������ �������</h1>'))
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

    /**
     * @param  list<string>  $areas
     */
    private function createUserWithAreas(array $areas): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'sales_book_role_'.uniqid(),
            'display_name' => 'Sales book role',
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

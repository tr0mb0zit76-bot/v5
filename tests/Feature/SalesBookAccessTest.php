<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SalesBookArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesBookAccessTest extends TestCase
{
    use RefreshDatabase;

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

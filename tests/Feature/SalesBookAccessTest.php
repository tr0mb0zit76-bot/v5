<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesBookAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_page_is_forbidden_without_sales_book_permissions(): void
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
            ->assertForbidden();
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

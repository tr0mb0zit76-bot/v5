<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\SidebarMenuCatalog;
use App\Support\SidebarMenuFavoritesResolver;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SidebarMenuFavoritesTest extends TestCase
{
    public function test_user_can_save_sidebar_favorite_menu_keys(): void
    {
        if (! Schema::hasColumn('users', 'ui_preferences')) {
            $this->markTestSkipped('Колонка users.ui_preferences недоступна.');
        }

        $role = Role::query()->create([
            'name' => 'manager_fav',
            'display_name' => 'Manager',
            'visibility_areas' => ['dashboard', 'orders', 'leads', 'contractors'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->patch(route('profile.sidebar-favorites'), [
                'sidebar_favorite_keys' => ['orders', 'leads', 'contractors'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(
            ['orders', 'leads', 'contractors'],
            $user->ui_preferences['sidebar_favorite_keys'] ?? [],
        );
    }

    public function test_inaccessible_menu_keys_are_stripped_from_favorites(): void
    {
        if (! Schema::hasColumn('users', 'ui_preferences')) {
            $this->markTestSkipped('Колонка users.ui_preferences недоступна.');
        }

        $role = Role::query()->create([
            'name' => 'orders_only',
            'display_name' => 'Orders only',
            'visibility_areas' => ['orders'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->patch(route('profile.sidebar-favorites'), [
                'sidebar_favorite_keys' => ['orders', 'leads', 'finance-salary'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(['orders'], $user->ui_preferences['sidebar_favorite_keys'] ?? []);
    }

    public function test_sidebar_favorites_resolver_builds_items_for_inertia(): void
    {
        if (! Schema::hasColumn('users', 'ui_preferences')) {
            $this->markTestSkipped('Колонка users.ui_preferences недоступна.');
        }

        $role = Role::query()->create([
            'name' => 'planner',
            'display_name' => 'Planner',
            'visibility_areas' => ['orders', 'tasks', 'kanban'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'ui_preferences' => [
                'sidebar_favorite_keys' => ['tasks', 'orders'],
            ],
        ]);

        $payload = SidebarMenuFavoritesResolver::forInertiaUser($user);

        $this->assertNotNull($payload);
        $this->assertSame(['tasks', 'orders'], $payload['keys']);
        $this->assertSame(SidebarMenuCatalog::maxFavorites(), $payload['max']);
        $this->assertSame(
            [
                ['key' => 'tasks', 'label' => 'Задачи', 'href' => '/tasks'],
                ['key' => 'orders', 'label' => 'Заказы', 'href' => '/orders'],
            ],
            $payload['items'],
        );
        $this->assertContains('kanban', $payload['candidate_keys']);
    }

    public function test_empty_favorites_removes_preference_key(): void
    {
        if (! Schema::hasColumn('users', 'ui_preferences')) {
            $this->markTestSkipped('Колонка users.ui_preferences недоступна.');
        }

        $role = Role::query()->create([
            'name' => 'dash',
            'display_name' => 'Dash',
            'visibility_areas' => ['dashboard'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'ui_preferences' => [
                'sidebar_favorite_keys' => ['dashboard'],
                'button_radius' => 'rounded',
            ],
        ]);

        $this->actingAs($user)
            ->patchJson(route('profile.sidebar-favorites'), [
                'sidebar_favorite_keys' => [],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertArrayNotHasKey('sidebar_favorite_keys', $user->ui_preferences ?? []);
        $this->assertSame('rounded', $user->ui_preferences['button_radius'] ?? null);
    }
}

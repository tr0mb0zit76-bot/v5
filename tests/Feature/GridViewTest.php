<?php

namespace Tests\Feature;

use App\Models\GridView;
use App\Models\Role;
use App\Models\User;
use App\Services\GridViewService;
use Tests\TestCase;

class GridViewTest extends TestCase
{
    public function test_user_can_create_and_list_private_orders_view(): void
    {
        $user = $this->makeUser(['orders']);

        $response = $this->actingAs($user)->postJson(route('grid-views.store'), [
            'grid_key' => 'orders',
            'name' => 'Мои активные',
            'visibility' => 'private',
            'column_state' => [
                ['colId' => 'order_number', 'hide' => false, 'width' => 140, 'order' => 0],
            ],
            'filter_state' => ['status_text' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'В пути']],
            'quick_search' => 'новафарм',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('view.name', 'Мои активные')
            ->assertJsonPath('view.grid_key', 'orders')
            ->assertJsonPath('view.can_manage', true);

        $viewId = (int) $response->json('view.id');

        $this->actingAs($user)
            ->getJson(route('grid-views.index', ['grid_key' => 'orders']))
            ->assertOk()
            ->assertJsonCount(1, 'views')
            ->assertJsonPath('views.0.id', $viewId);
    }

    public function test_user_cannot_read_foreign_private_view(): void
    {
        $owner = $this->makeUser(['orders']);
        $other = $this->makeUser(['orders']);

        $view = GridView::factory()->create([
            'owner_user_id' => $owner->id,
            'grid_key' => 'orders',
            'visibility' => 'private',
        ]);

        $this->actingAs($other)
            ->getJson(route('grid-views.show', $view))
            ->assertNotFound();
    }

    public function test_owner_can_update_pin_and_delete_view(): void
    {
        $user = $this->makeUser(['orders']);

        $view = GridView::factory()->create([
            'owner_user_id' => $user->id,
            'grid_key' => 'orders',
            'name' => 'Черновик',
        ]);

        $this->actingAs($user)
            ->patchJson(route('grid-views.update', $view), [
                'name' => 'Закреплённый',
                'is_pinned_sidebar' => true,
                'quick_search' => 'тест',
            ])
            ->assertOk()
            ->assertJsonPath('view.name', 'Закреплённый')
            ->assertJsonPath('view.is_pinned_sidebar', true)
            ->assertJsonPath('view.quick_search', 'тест');

        $this->actingAs($user)
            ->deleteJson(route('grid-views.destroy', $view))
            ->assertOk();

        $this->assertDatabaseMissing('grid_views', ['id' => $view->id]);
    }

    public function test_workspace_view_is_visible_to_users_with_grid_access(): void
    {
        $admin = $this->makeUser(['orders'], roleName: 'admin');
        $manager = $this->makeUser(['orders']);

        $view = GridView::factory()->create([
            'owner_user_id' => $admin->id,
            'grid_key' => 'orders',
            'name' => 'Общий реестр',
            'visibility' => 'workspace',
        ]);

        $this->actingAs($manager)
            ->getJson(route('grid-views.index', ['grid_key' => 'orders']))
            ->assertOk()
            ->assertJsonFragment(['id' => $view->id, 'name' => 'Общий реестр']);
    }

    public function test_pinned_views_are_exposed_for_sidebar(): void
    {
        $user = $this->makeUser(['orders', 'documents']);

        GridView::factory()->create([
            'owner_user_id' => $user->id,
            'grid_key' => 'orders',
            'name' => 'В работе',
            'is_pinned_sidebar' => true,
        ]);

        GridView::factory()->create([
            'owner_user_id' => $user->id,
            'grid_key' => 'documents',
            'name' => 'Без оригиналов',
            'is_pinned_sidebar' => true,
        ]);

        GridView::factory()->create([
            'owner_user_id' => $user->id,
            'grid_key' => 'orders',
            'name' => 'Скрытый',
            'is_pinned_sidebar' => false,
        ]);

        $pinned = app(GridViewService::class)->pinnedForSidebar($user);

        $this->assertCount(2, $pinned);
        $this->assertSame(['Без оригиналов', 'В работе'], collect($pinned)->pluck('name')->sort()->values()->all());
        $this->assertStringContainsString('view=', $pinned[0]['url']);
    }

    public function test_user_without_grid_access_cannot_create_view(): void
    {
        $user = $this->makeUser(['dashboard']);

        $this->actingAs($user)
            ->postJson(route('grid-views.store'), [
                'grid_key' => 'orders',
                'name' => 'Недоступно',
            ])
            ->assertForbidden();
    }

    public function test_supervisor_can_share_view_with_role(): void
    {
        $supervisorRole = Role::query()->create([
            'name' => 'supervisor',
            'display_name' => 'Руководитель',
            'visibility_areas' => ['orders'],
        ]);

        $managerRole = Role::query()->create([
            'name' => 'manager_share_'.uniqid(),
            'display_name' => 'Менеджер',
            'visibility_areas' => ['orders'],
        ]);

        $supervisor = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'is_active' => true,
        ]);

        $manager = User::factory()->create([
            'role_id' => $managerRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($supervisor)->postJson(route('grid-views.store'), [
            'grid_key' => 'orders',
            'name' => 'Для менеджеров',
            'visibility' => 'role',
            'shared_with' => [
                'role_ids' => [$managerRole->id],
            ],
            'filter_state' => [],
        ]);

        $response->assertCreated()->assertJsonPath('view.visibility', 'role');

        $viewId = (int) $response->json('view.id');

        $this->actingAs($manager)
            ->getJson(route('grid-views.index', ['grid_key' => 'orders']))
            ->assertOk()
            ->assertJsonFragment(['id' => $viewId, 'name' => 'Для менеджеров']);

        $this->actingAs($supervisor)
            ->getJson(route('grid-views.index', ['grid_key' => 'orders']))
            ->assertOk()
            ->assertJsonPath('can_share', true)
            ->assertJsonStructure(['share_options' => ['roles', 'users']]);
    }

    public function test_manager_cannot_set_non_private_visibility(): void
    {
        $user = $this->makeUser(['orders']);

        $this->actingAs($user)
            ->postJson(route('grid-views.store'), [
                'grid_key' => 'orders',
                'name' => 'Попытка workspace',
                'visibility' => 'workspace',
            ])
            ->assertStatus(422);
    }

    public function test_non_owner_cannot_update_foreign_view(): void
    {
        $owner = $this->makeUser(['orders']);
        $other = $this->makeUser(['orders']);

        $view = GridView::factory()->create([
            'owner_user_id' => $owner->id,
            'grid_key' => 'orders',
        ]);

        $this->actingAs($other)
            ->patchJson(route('grid-views.update', $view), [
                'name' => 'Взлом',
            ])
            ->assertStatus(422);
    }

    /**
     * @param  list<string>  $areas
     * @param  array<string, string>  $scopes
     */
    private function makeUser(array $areas, array $scopes = [], string $roleName = 'manager'): User
    {
        $role = Role::query()->create([
            'name' => $roleName === 'admin' ? 'admin' : 'grid_view_test_'.uniqid(),
            'display_name' => $roleName === 'admin' ? 'Admin' : 'Grid View Test',
            'permissions' => $roleName === 'admin' ? ['*'] : [],
            'visibility_areas' => $areas,
            'visibility_scopes' => $scopes,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}

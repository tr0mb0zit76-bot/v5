<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CabinetInAppNotification;
use Tests\TestCase;

class CabinetInAppNotificationsTest extends TestCase
{
    public function test_cabinet_summary_requires_authentication(): void
    {
        $this->getJson(route('cabinet-notifications.summary'))
            ->assertUnauthorized();
    }

    public function test_cabinet_summary_returns_badge_shape_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('cabinet-notifications.summary'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('badges.total', 0)
            ->assertJsonPath('badges.orders', 0)
            ->assertJsonPath('badges.tasks', 0)
            ->assertJsonStructure(['unread_count', 'latest', 'badges' => ['total', 'orders', 'tasks']]);
    }

    public function test_mark_all_read_clears_unread_notifications(): void
    {
        $user = User::factory()->create();
        $user->notify(new CabinetInAppNotification(
            'task_assigned',
            'Тест',
            'Текст',
            '/tasks',
            [],
        ));

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
        ]);

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());

        $this->actingAs($user)->postJson(route('cabinet-notifications.read-all'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_mark_unread_restores_unread_state(): void
    {
        $user = User::factory()->create();
        $user->notify(new CabinetInAppNotification(
            'task_assigned',
            'Тест',
            'Текст',
            '/tasks',
            [],
        ));

        $notification = $user->fresh()->notifications()->first();
        $notification->markAsRead();

        $this->actingAs($user)->postJson(route('cabinet-notifications.unread', $notification->id))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    public function test_destroy_removes_notification(): void
    {
        $user = User::factory()->create();
        $user->notify(new CabinetInAppNotification(
            'task_assigned',
            'Тест',
            'Текст',
            '/tasks',
            [],
        ));

        $notification = $user->fresh()->notifications()->first();

        $this->actingAs($user)->deleteJson(route('cabinet-notifications.destroy', $notification->id))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(0, $user->fresh()->notifications()->count());
    }
}

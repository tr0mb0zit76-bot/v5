<?php

namespace Tests\Feature\Disposition;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Disposition\DispositionReminderService;
use App\Support\DispositionSlot;
use Tests\Support\CreatesInTransitOrders;
use Tests\TestCase;

class DispositionReminderTest extends TestCase
{
    use CreatesInTransitOrders;

    protected function setUp(): void
    {
        parent::setUp();

        config(['disposition.reminder_tasks_enabled' => true]);
    }

    public function test_command_creates_task_for_unfilled_morning_slot(): void
    {
        $manager = $this->makeUser(['orders', 'tasks'], ['orders' => 'all']);

        $order = $this->createInTransitOrder([
            'manager_id' => $manager->id,
            'order_number' => 'DISP-REM-1',
        ]);

        $this->artisan('disposition:remind-unfilled-slots morning')
            ->assertSuccessful();

        $this->assertDatabaseHas('tasks', [
            'order_id' => $order->id,
            'responsible_id' => $manager->id,
            'status' => 'new',
        ]);

        $task = Task::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($task);
        $this->assertSame(
            app(DispositionReminderService::class)->slotKey($order->id, now()->toDateString(), 'morning'),
            $task->meta['disposition_slot_key'] ?? null,
        );
    }

    public function test_reminders_are_not_created_when_disabled(): void
    {
        config(['disposition.reminder_tasks_enabled' => false]);

        $manager = $this->makeUser(['orders', 'tasks'], ['orders' => 'all']);

        $order = $this->createInTransitOrder([
            'manager_id' => $manager->id,
            'order_number' => 'DISP-REM-OFF',
        ]);

        $this->artisan('disposition:remind-unfilled-slots morning')
            ->assertSuccessful();

        $this->assertDatabaseMissing('tasks', [
            'order_id' => $order->id,
        ]);

        $created = app(DispositionReminderService::class)->createRemindersForSlot(DispositionSlot::Morning);

        $this->assertSame(0, $created);
    }

    public function test_upsert_location_closes_disposition_reminder_task(): void
    {
        $manager = $this->makeUser(['orders', 'tasks'], ['orders' => 'all']);

        $order = $this->createInTransitOrder(['manager_id' => $manager->id]);

        app(DispositionReminderService::class)->createRemindersForSlot(DispositionSlot::Morning);

        $this->assertDatabaseHas('tasks', [
            'order_id' => $order->id,
            'status' => 'new',
        ]);

        $this->actingAs($manager)->postJson(route('disposition.entries.upsert'), [
            'order_id' => $order->id,
            'date' => now()->toDateString(),
            'slot' => 'morning',
            'location' => 'Красноярск',
            'comment' => null,
        ])->assertOk();

        $this->assertDatabaseHas('tasks', [
            'order_id' => $order->id,
            'status' => 'done',
        ]);
    }

    public function test_comment_upsert_creates_order_activity_event(): void
    {
        $manager = $this->makeUser(['orders'], ['orders' => 'all']);

        $order = $this->createInTransitOrder(['manager_id' => $manager->id]);

        $this->actingAs($manager)->postJson(route('disposition.entries.upsert'), [
            'order_id' => $order->id,
            'date' => '2026-05-28',
            'slot' => 'evening',
            'location' => 'Новосибирск',
            'comment' => 'Стоим на выгрузке',
        ])->assertOk();

        $this->assertDatabaseHas('activity_events', [
            'subject_type' => $order->getMorphClass(),
            'subject_id' => $order->id,
            'event_type' => 'disposition_comment',
        ]);
    }

    /**
     * @param  list<string>  $areas
     * @param  array<string, string>  $scopes
     */
    private function makeUser(array $areas, array $scopes = []): User
    {
        $role = Role::query()->create([
            'name' => 'disposition_reminder_'.uniqid(),
            'display_name' => 'Disposition Reminder',
            'permissions' => [],
            'visibility_areas' => $areas,
            'visibility_scopes' => $scopes,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}

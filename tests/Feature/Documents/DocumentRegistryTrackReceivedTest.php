<?php

namespace Tests\Feature\Documents;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderCompensationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentRegistryTrackReceivedTest extends TestCase
{
    use RefreshDatabase;

    public function test_clerk_can_set_track_received_date_from_documents_registry(): void
    {
        if (! Schema::hasColumn('orders', 'track_received_date_customer')) {
            $this->markTestSkipped('Колонка track_received_date_customer недоступна.');
        }

        $clerk = $this->makeClerkUser();
        $order = $this->makeOrderNeedingCustomerTrackReceived($clerk);

        $this->actingAs($clerk)
            ->patchJson(route('documents.orders.track-received', $order), [
                'field' => 'track_received_date_customer',
                'value' => '2026-06-04',
            ])
            ->assertOk()
            ->assertJson([
                'field' => 'track_received_date_customer',
                'value' => '2026-06-04',
            ]);

        $this->assertSame('2026-06-04', $order->fresh()->track_received_date_customer?->toDateString());
    }

    public function test_manager_cannot_set_track_received_date_from_documents_registry(): void
    {
        if (! Schema::hasColumn('orders', 'track_received_date_customer')) {
            $this->markTestSkipped('Колонка track_received_date_customer недоступна.');
        }

        $manager = $this->makeManagerUser();
        $order = $this->makeOrderNeedingCustomerTrackReceived($manager);

        $this->actingAs($manager)
            ->patchJson(route('documents.orders.track-received', $order), [
                'field' => 'track_received_date_customer',
                'value' => '2026-06-04',
            ])
            ->assertForbidden();
    }

    public function test_documents_index_includes_track_received_flags_for_clerk(): void
    {
        $clerk = $this->makeClerkUser();
        $order = $this->makeOrderNeedingCustomerTrackReceived($clerk);

        $this->actingAs($clerk)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can_edit_track_received_dates', true)
                ->has('rows', 1)
                ->where('rows.0.order_id', $order->id)
                ->where('rows.0.needs_track_received_date_customer', true)
                ->where('rows.0.track_received_date_customer', null),
            );
    }

    public function test_track_received_update_rejected_when_not_required_by_schedule(): void
    {
        if (! Schema::hasColumn('orders', 'track_received_date_customer')) {
            $this->markTestSkipped('Колонка track_received_date_customer недоступна.');
        }

        $clerk = $this->makeClerkUser();

        $order = Order::factory()->create([
            'manager_id' => $clerk->id,
            'customer_payment_form' => 'bank_transfer',
            'payment_terms' => json_encode([
                'client' => [
                    'payment_schedule' => [
                        'installments' => [
                            ['percent' => 100, 'basis' => 'fttn', 'offset_days' => 3],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        FinancialTerm::factory()->create([
            'order_id' => $order->id,
            'payment_terms_snapshot' => $order->payment_terms,
        ]);

        $this->actingAs($clerk)
            ->patchJson(route('documents.orders.track-received', $order), [
                'field' => 'track_received_date_customer',
                'value' => '2026-06-04',
            ])
            ->assertStatus(422);
    }

    public function test_track_received_update_resyncs_payment_schedules(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasColumn('orders', 'track_received_date_customer')) {
            $this->markTestSkipped('Таблица payment_schedules или колонка track_received недоступна.');
        }

        $clerk = $this->makeClerkUser();
        $order = $this->makeOrderNeedingCustomerTrackReceived($clerk);

        app(OrderCompensationService::class)->syncPaymentSchedules($order->fresh());

        $this->assertNull(
            $order->fresh()->paymentSchedules()->where('party', 'customer')->value('planned_date'),
        );

        $this->actingAs($clerk)
            ->patchJson(route('documents.orders.track-received', $order), [
                'field' => 'track_received_date_customer',
                'value' => '2026-06-04',
            ])
            ->assertOk();

        $this->assertNotNull(
            $order->fresh()->paymentSchedules()->where('party', 'customer')->value('planned_date'),
        );
    }

    private function makeClerkUser(): User
    {
        $role = Role::query()->create([
            'name' => 'clerk',
            'display_name' => 'Делопроизводитель',
            'permissions' => [],
            'visibility_areas' => ['documents', 'orders'],
            'visibility_scopes' => ['documents' => 'all', 'orders' => 'all'],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function makeManagerUser(): User
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Менеджер',
            'permissions' => [],
            'visibility_areas' => ['documents', 'orders'],
            'visibility_scopes' => ['documents' => 'own', 'orders' => 'own'],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function makeOrderNeedingCustomerTrackReceived(User $manager): Order
    {
        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'DOC-TR-'.uniqid(),
            'customer_payment_form' => 'bank_transfer',
            'track_received_date_customer' => null,
            'payment_terms' => json_encode([
                'client' => [
                    'payment_schedule' => [
                        'installments' => [
                            ['percent' => 100, 'basis' => 'ottn', 'offset_days' => 3, 'offset_unit' => 'bank_days'],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        FinancialTerm::factory()->create([
            'order_id' => $order->id,
            'payment_terms_snapshot' => $order->payment_terms,
        ]);

        return $order;
    }
}

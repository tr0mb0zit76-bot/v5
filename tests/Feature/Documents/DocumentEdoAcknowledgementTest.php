<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentEdoAcknowledgementTest extends TestCase
{
    public function test_clerk_can_mark_closing_document_received_via_edo(): void
    {
        if (! Schema::hasTable('order_document_edo_acknowledgements')) {
            $this->markTestSkipped('Таблица order_document_edo_acknowledgements недоступна.');
        }

        $clerk = $this->makeClerkUser();
        $customer = Contractor::query()->create([
            'name' => 'ООО Клиент',
            'type' => 'customer',
        ]);

        $order = Order::factory()->create([
            'manager_id' => $clerk->id,
            'customer_id' => $customer->id,
            'customer_payment_form' => 'bank_transfer',
        ]);

        $this->actingAs($clerk)
            ->patchJson(route('documents.orders.edo-acknowledgement', $order), [
                'party' => 'customer',
                'document_type' => 'upd',
                'slot_key' => 'customer-all',
                'received_via_edo' => true,
                'document_number' => 'UPD-77',
                'document_date' => '2026-06-12',
            ])
            ->assertOk()
            ->assertJsonPath('acknowledgement.document_number', 'UPD-77')
            ->assertJsonPath('acknowledgement.received_via_edo', true);

        $this->assertDatabaseHas('order_document_edo_acknowledgements', [
            'order_id' => $order->id,
            'party' => 'customer',
            'document_type' => 'upd',
            'document_number' => 'UPD-77',
            'received_via_edo' => true,
        ]);
    }

    public function test_edo_acknowledgement_requires_document_number_when_marked_received(): void
    {
        if (! Schema::hasTable('order_document_edo_acknowledgements')) {
            $this->markTestSkipped('Таблица order_document_edo_acknowledgements недоступна.');
        }

        $clerk = $this->makeClerkUser();
        $order = Order::factory()->create([
            'manager_id' => $clerk->id,
            'customer_payment_form' => 'bank_transfer',
        ]);

        $this->actingAs($clerk)
            ->patchJson(route('documents.orders.edo-acknowledgement', $order), [
                'party' => 'customer',
                'document_type' => 'upd',
                'slot_key' => 'customer-all',
                'received_via_edo' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_number']);
    }

    public function test_manager_cannot_mark_edo_acknowledgement(): void
    {
        if (! Schema::hasTable('order_document_edo_acknowledgements')) {
            $this->markTestSkipped('Таблица order_document_edo_acknowledgements недоступна.');
        }

        $manager = $this->makeManagerUser();
        $order = Order::factory()->create(['manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->patchJson(route('documents.orders.edo-acknowledgement', $order), [
                'party' => 'customer',
                'document_type' => 'upd',
                'received_via_edo' => true,
                'document_number' => 'UPD-1',
            ])
            ->assertForbidden();
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
}
